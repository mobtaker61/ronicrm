<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\RonibotUrlDefaults;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $appKey;

    protected string $authKey;

    protected string $apiUrl;

    protected bool $enabled;

    public function __construct()
    {
        $settings = Setting::getForOrganization('ronibot', []);
        $this->appKey = $settings['appkey'] ?? '';
        $this->authKey = $settings['authkey'] ?? '';
        $fromEnv = RonibotUrlDefaults::createMessageUrl();
        $fromDb = RonibotUrlDefaults::normalizeCreateMessageUrl((string) ($settings['api_url'] ?? ''));
        $this->apiUrl = $fromEnv !== '' ? $fromEnv : ($fromDb !== '' ? $fromDb : 'https://ronibot.com/api/create-message');
        $this->enabled = $settings['enabled'] ?? false;
    }

    public function sendMessage(string $phone, string $message, ?string $fileUrl = null): array
    {
        if (! $this->enabled) {
            return [
                'success' => false,
                'error' => 'Ronibot is not enabled',
                'status' => 'failed',
            ];
        }

        if (empty($this->appKey) || empty($this->authKey)) {
            Log::error('Ronibot credentials are missing');

            return [
                'success' => false,
                'error' => 'Ronibot credentials are not configured',
                'status' => 'failed',
            ];
        }

        try {
            $phone = $this->formatPhone($phone);

            // ساخت POST data
            $postData = [
                'appkey' => $this->appKey,
                'authkey' => $this->authKey,
                'to' => $phone,
                'sandbox' => 'false',
            ];

            // اگر فایل وجود دارد، اضافه کن
            if ($fileUrl) {
                $postData['file'] = $fileUrl;
                // اگر message خالی است و فایل داریم، یک message پیش‌فرض بگذار
                // API Ronibot نیاز به message دارد حتی اگر فایل هم داشته باشیم
                // استفاده از یک متن ساده به جای emoji برای سازگاری بیشتر
                $postData['message'] = trim($message) ?: 'File';
            } else {
                // اگر فایل نداریم، message اجباری است
                $postData['message'] = $message;
            }

            /** @var \Illuminate\Http\Client\Response $response */
            // Increase timeout to 120 seconds for file uploads
            $timeout = $fileUrl ? 120 : 60;
            $response = Http::timeout($timeout)->asForm()->post($this->apiUrl, $postData);

            if ($response->successful()) {
                $responseData = $response->json();

                // Check if API returned an error in the response body
                if (isset($responseData['success']) && $responseData['success'] === false) {
                    $error = $responseData['message'] ?? 'Unknown error from API';
                    if (isset($responseData['data']) && is_array($responseData['data'])) {
                        $errors = [];
                        foreach ($responseData['data'] as $field => $messages) {
                            $errors[] = $field.': '.implode(', ', $messages);
                        }
                        $error = implode(' | ', $errors);
                    }

                    return [
                        'success' => false,
                        'error' => $error,
                        'status' => 'failed',
                        'response' => $responseData,
                    ];
                }

                return [
                    'success' => true,
                    'message_id' => $responseData['message_id'] ?? null,
                    'status' => 'sent',
                    'response' => $responseData,
                ];
            }

            $responseBody = $response->body();
            $responseJson = $response->json();
            $error = ($responseJson && isset($responseJson['error'])) ? $responseJson['error'] : ($responseBody ?: 'Unknown error');

            return [
                'success' => false,
                'error' => $error,
                'status' => 'failed',
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Handle timeout specifically - message might have been sent
            $errorMessage = $e->getMessage();

            // If it's a timeout, we can't be sure if message was sent or not
            // But since user reported messages were sent despite timeout, we'll mark as sent
            // with a warning note
            if (str_contains($errorMessage, 'timed out') || str_contains($errorMessage, 'timeout')) {
                return [
                    'success' => true, // Assume success if timeout (message might have been sent)
                    'status' => 'sent',
                    'warning' => 'Message sent but timeout occurred. Please verify delivery.',
                    'error' => $errorMessage,
                ];
            }

            return [
                'success' => false,
                'error' => $errorMessage,
                'status' => 'failed',
            ];
        } catch (\Exception $e) {
            // Only log critical errors
            if (! str_contains($e->getMessage(), 'timed out')) {
                Log::error('WhatsApp API Exception: '.$e->getMessage());
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    protected function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // اگر شماره با + شروع می‌شود، + را حذف کن
        $phone = ltrim($phone, '+');

        // اگر شماره با 00 شروع می‌شود، 00 را حذف کن
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        // برای شماره‌های امارات (971) یا ایران (98) یا سایر کشورها
        // شماره را به همان صورت که هست برگردان (بدون تغییر)
        // چون ممکن است کاربر خودش country code را وارد کرده باشد

        return $phone;
    }
}
