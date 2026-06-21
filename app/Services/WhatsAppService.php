<?php

namespace App\Services;

use App\Support\OrganizationContext;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        protected WhatsAppYarApiService $api
    ) {}

    /**
     * @return array{success: bool, error?: string, status: string, message_id?: string|null, warning?: string, response?: mixed}
     */
    public function sendMessage(string $phone, string $message, ?string $fileUrl = null, ?string $mimeType = null): array
    {
        $organizationId = OrganizationContext::getOrganizationId();
        $settings = WhatsAppSettings::get($organizationId);

        if (! ($settings['enabled'] ?? false)) {
            return [
                'success' => false,
                'error' => 'WhatsApp is not enabled',
                'status' => 'failed',
            ];
        }

        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '') {
            return [
                'success' => false,
                'error' => 'WhatsApp session is not configured',
                'status' => 'failed',
            ];
        }

        if (! WhatsAppSettings::isConfigured($organizationId)) {
            Log::error('WhatsAppYar API key is missing');

            return [
                'success' => false,
                'error' => 'WhatsApp API key is not configured',
                'status' => 'failed',
            ];
        }

        try {
            $chatId = WhatsAppYarApiService::resolveChatId($phone);
            $api = $this->api->forOrganization($organizationId);

            if ($fileUrl) {
                $endpoint = $this->resolveMediaEndpoint($fileUrl, $mimeType);
                $caption = trim($message) !== '' ? $message : null;
                $response = $api->sendMedia(
                    $sessionId,
                    $endpoint,
                    $chatId,
                    $fileUrl,
                    $caption,
                    $mimeType,
                    basename(parse_url($fileUrl, PHP_URL_PATH) ?: 'file')
                );
            } else {
                $response = $api->sendText($sessionId, $chatId, $message);
            }

            $messageId = $this->extractMessageId($response);

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => 'sent',
                'response' => $response,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $errorMessage = $e->getMessage();
            if (str_contains(strtolower($errorMessage), 'timeout')) {
                return [
                    'success' => true,
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
        } catch (\Throwable $e) {
            if (! str_contains(strtolower($e->getMessage()), 'timeout')) {
                Log::error('WhatsApp API Exception: '.$e->getMessage());
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    protected function resolveMediaEndpoint(string $fileUrl, ?string $mimeType): string
    {
        $mime = strtolower((string) ($mimeType ?? ''));
        if ($mime === '') {
            $path = parse_url($fileUrl, PHP_URL_PATH) ?? '';
            $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image/'.$ext,
                'mp4', 'mov', 'm4v' => 'video/mp4',
                'mp3', 'wav', 'ogg', 'm4a' => 'audio/'.$ext,
                default => '',
            };
        }

        if (str_starts_with($mime, 'image/')) {
            return 'send-image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'send-video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'send-audio';
        }

        return 'send-document';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractMessageId(array $response): ?string
    {
        foreach (['id', 'messageId', 'message_id'] as $key) {
            if (! empty($response[$key])) {
                return (string) $response[$key];
            }
        }

        $nested = $response['data'] ?? null;
        if (is_array($nested)) {
            foreach (['id', 'messageId', 'message_id'] as $key) {
                if (! empty($nested[$key])) {
                    return (string) $nested[$key];
                }
            }
        }

        return null;
    }
}
