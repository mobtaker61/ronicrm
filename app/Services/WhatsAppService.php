<?php

namespace App\Services;

use App\Models\Setting;
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
        $settings = Setting::get('ronibot', []);
        $this->appKey = $settings['appkey'] ?? '';
        $this->authKey = $settings['authkey'] ?? '';
        $this->apiUrl = $settings['api_url'] ?? 'https://ronibot.com/api/create-message';
        $this->enabled = $settings['enabled'] ?? false;
    }

    public function sendMessage(string $phone, string $message, ?string $fileUrl = null): array
    {
        if (!$this->enabled) {
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

            Log::info('Sending WhatsApp message via Ronibot', [
                'to' => $phone,
                'has_file' => !empty($fileUrl),
                'file_url' => $fileUrl,
                'message' => $message,
                'post_data' => $postData,
            ]);

            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::asForm()->post($this->apiUrl, $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if API returned an error in the response body
                if (isset($responseData['success']) && $responseData['success'] === false) {
                    $error = $responseData['message'] ?? 'Unknown error from API';
                    if (isset($responseData['data']) && is_array($responseData['data'])) {
                        $errors = [];
                        foreach ($responseData['data'] as $field => $messages) {
                            $errors[] = $field . ': ' . implode(', ', $messages);
                        }
                        $error = implode(' | ', $errors);
                    }
                    
                    Log::error('WhatsApp API returned error in response', [
                        'response' => $responseData,
                        'error' => $error,
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => $error,
                        'status' => 'failed',
                        'response' => $responseData,
                    ];
                }
                
                Log::info('WhatsApp message sent successfully', [
                    'response' => $responseData,
                ]);

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
            
            Log::error('WhatsApp API Error', [
                'status' => $response->status(),
                'error' => $error,
                'response' => $responseBody,
                'request_data' => [
                    'to' => $phone,
                    'has_file' => !empty($fileUrl),
                    'file_url' => $fileUrl,
                    'message' => $message,
                ],
            ]);

            return [
                'success' => false,
                'error' => $error,
                'status' => 'failed',
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp API Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

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
