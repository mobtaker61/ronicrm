<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Services\CampaignMessageComposer;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public CampaignRecipient $recipient,
        public string $type,
        public string $content,
        public ?string $subject = null,
        public ?string $image = null,
        public ?array $whatsappSettings = null,
    ) {}

    public function handle(): void
    {
        try {
            $customer = $this->recipient->customer;
            $result = null;

            if ($this->type === 'whatsapp') {
                $whatsappService = app(WhatsAppService::class);
                // Get WhatsApp contact (not phone, as they are separate entities)
                $whatsappContact = $customer->contacts()->where('type', 'whatsapp')->first();
                $phone = $whatsappContact?->value;

                if (! $phone) {
                    $this->recipient->update([
                        'status' => 'failed',
                        'error_message' => 'No WhatsApp contact found',
                    ]);

                    return;
                }

                $composer = app(CampaignMessageComposer::class);
                $message = $composer->render(
                    $this->content,
                    $customer,
                    $this->whatsappSettings,
                    true
                );

                // اگر تصویر وجود دارد، URL کامل آن را بساز
                $imageUrl = null;
                if ($this->image) {
                    $imageUrl = asset('storage/'.$this->image);
                }

                $result = $whatsappService->sendMessage($phone, $message, $imageUrl);
            } elseif ($this->type === 'email') {
                $emailService = app(EmailService::class);
                $email = $customer->email ?? $customer->contacts()->where('type', 'email')->first()?->value;

                if (! $email) {
                    $this->recipient->update([
                        'status' => 'failed',
                        'error_message' => 'No email address found',
                    ]);

                    return;
                }

                $composer = app(CampaignMessageComposer::class);
                $content = $composer->render(
                    $this->content,
                    $customer,
                    $this->whatsappSettings,
                    false
                );
                $subject = $this->subject
                    ? $composer->render($this->subject, $customer, $this->whatsappSettings, false)
                    : 'Campaign Message';

                $result = $emailService->sendHtmlEmail($email, $subject, $content);
            }

            if ($result && $result['success']) {
                $this->recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $this->recipient->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Campaign message sending failed: '.$e->getMessage());
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
