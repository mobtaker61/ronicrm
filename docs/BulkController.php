<?php

/**
 * مرجع کنترلر Ronibot/whatsender (سرور جدا از RoniCRM).
 * همگام‌سازی گروه واتساپ به اندپوینت POST /wpwebhook-group روی **دامنهٔ CRM** (Laravel RoniCRM) می‌رود؛
 * اینجا لازم نیست مگر بخواهید پروکسی کنید. نود Baileys باید APP_GROUP_WEBHOOK_URL یا APP_CRM_BASE_URL را به CRM بدهد.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bulkrequest;
use App\Models\App;
use App\Models\Device;
use App\Models\Template;
use App\Models\User;
use App\Models\Webhook;
use App\Traits\Whatsapp;
use Http;
use Illuminate\Http\Request;

class BulkController extends Controller
{
    use Whatsapp;

    /**
     * sent message
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function submitRequest(Bulkrequest $request)
    {

        $user = User::where('status', 1)->where('will_expire', '>', now())->where('authkey', $request->authkey)->first();
        $app = App::where('key', $request->appkey)->whereHas('device')->with('device')->where('status', 1)->first();

        if ($user == null || $app == null) {
            return response()->json(['error' => 'Invalid Auth and AppKey'], 401);
        }

        if (getUserPlanData('messages_limit', $user->id) == false) {
            return response()->json([
                'message' => __('Maximum Monthly Messages Limit Exceeded'),
            ], 401);
        }

        if (! empty($request->template_id)) {

            $template = Template::where('user_id', $user->id)->where('uuid', $request->template_id)->where('status', 1)->first();
            if (empty($template)) {
                return response()->json(['error' => 'Template Not Found'], 401);
            }

            if (isset($template->body['text'])) {
                $body = $template->body;
                $text = $this->formatText($template->body['text'], [], $user);
                $text = $this->formatCustomText($text, $request->variables ?? []);
                $body['text'] = $text;
            } else {
                $body = $template->body;
            }
            $type = $template->type;

        } else {

            $text = $this->formatText($request->message);
            if (! empty($request->file)) {

                $explode = explode('.', $request->file);
                $file_type = strtolower(end($explode));
                $extentions = [
                    'jpg' => 'image',
                    'jpeg' => 'image',
                    'png' => 'image',
                    'webp' => 'image',
                    'pdf' => 'document',
                    'docx' => 'document',
                    'xlsx' => 'document',
                    'csv' => 'document',
                    'txt' => 'document',
                ];

                if (! isset($extentions[$file_type])) {
                    $validators['error'] = 'file type should be jpg,jpeg,png,webp,pdf,docx,xlsx,csv,txt';

                    return response()->json($validators, 403);
                }

                $body[$extentions[$file_type]] = ['url' => $request->file];
                $body['caption'] = $text;
                $type = 'text-with-media';
            } else {
                $body['text'] = $text;
                $type = 'plain-text';
            }

        }

        if (! isset($body)) {
            return response()->json(['error' => 'Request Failed'], 401);
        }

        try {

            $response = $this->messageSend($body, $app->device_id, $request->to, $type, true);

            if ($response['status'] == 200) {

                $logs['user_id'] = $user->id;
                $logs['device_id'] = $app->device_id;
                $logs['app_id'] = $app->id;
                $logs['from'] = $app->device->phone ?? null;
                $logs['to'] = $request->to;
                $logs['template_id'] = $template->id ?? null;
                $logs['type'] = 'from_api';

                $this->saveLog($logs);

                return response()->json(['message_status' => 'Success', 'data' => [
                    'from' => $app->device->phone ?? null,
                    'to' => $request->to,
                    'status_code' => 200,
                ]], 200);
            } else {
                return response()->json(['error' => 'Request Failed'], 401);

            }

        } catch (Exception $e) {

            return response()->json(['error' => 'Request Failed'], 401);
        }

    }

    /**
     * set status device
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function setStatus($device_id, $status)
    {

        $device_id = str_replace('device_', '', $device_id);

        $device = Device::where('id', $device_id)->first();
        if (! empty($device)) {
            $device->status = $status;
            $device->save();
        }

    }

    /**
     * receive webhook response
     *
     * @return \Illuminate\Http\Response
     */
    public function webHook(Request $request, $device_id)
    {
        \Log::info('WEBHOOK RAW:', $request->all());

        $device_id_clean = str_replace('device_', '', $device_id);

        $device = Device::with('user')
            ->whereHas('user', function ($q) {
                $q->where('will_expire', '>', now());
            })
            ->where('id', $device_id_clean)
            ->first();

        if (! $device) {
            \Log::error('Device not found');

            return response()->json(['error' => 'Device not found'], 404);
        }

        // ✅ جدید
        $payload = $request->input('payload');

        if (! $payload) {
            \Log::error('Payload missing');

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $type = $payload['type'] ?? null;
        $data = $payload['data'][0] ?? null;

        if (! $data) {
            \Log::error('Data missing');

            return response()->json(['error' => 'Invalid data'], 400);
        }

        // sender
        $request_from = null;
        if (isset($data['key']['remoteJidAlt'])) {
            $request_from = explode('@', $data['key']['remoteJidAlt'])[0];
        }

        // message extract
        $msgObj = $data['message'] ?? null;

        $message = null;
        $mediaUrl = $data['media']['url'] ?? null;

        if (isset($msgObj['conversation'])) {
            $message = $msgObj['conversation'];
        } elseif (isset($msgObj['extendedTextMessage']['text'])) {
            $message = $msgObj['extendedTextMessage']['text'];
        } elseif (isset($msgObj['imageMessage']['caption'])) {
            $message = $msgObj['imageMessage']['caption'];
        } elseif (isset($msgObj['videoMessage']['caption'])) {
            $message = $msgObj['videoMessage']['caption'];
        } elseif (isset($msgObj['documentMessage']['fileName'])) {
            $message = $msgObj['documentMessage']['fileName'];
        } elseif (isset($msgObj['documentMessage']['caption'])) {
            $message = $msgObj['documentMessage']['caption'];
        }

        // ✅ ذخیره webhook (حتی اگر متن نداشته باشه)
        if ($device->hook_url) {
            Webhook::create([
                'device_id' => $device->id,
                'user_id' => $device->user_id,
                'payload' => json_encode($request->all()),
                'hook' => $device->hook_url,
            ]);
        }

        // ✅ ارسال به CRM
        if ($device->hook_url) {
            try {
                $response = Http::post($device->hook_url, [
                    'payload' => $payload,
                    'sender' => $request_from,
                    'receiver' => $device->phone,
                    'mediaUrl' => $mediaUrl,
                    'text' => $message,
                ]);

                \Log::info('CRM RESPONSE', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                \Log::error('CRM ERROR: '.$e->getMessage());
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
