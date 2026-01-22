<?php

namespace App\Jobs;

use App\Models\CampaignRecipient;
use App\Services\EmailService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCampaignMessage implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public CampaignRecipient $recipient,
        public string $type,
        public string $content,
        public ?string $subject = null,
        public ?string $image = null
    ) {}

    public function handle(): void
    {
        try {
            $customer = $this->recipient->customer;
            $result = null;

            if ($this->type === 'whatsapp') {
                $whatsappService = app(WhatsAppService::class);
                $phone = $customer->phone ?? $customer->contacts()->where('type', 'phone')->first()?->value;
                
                if (!$phone) {
                    $this->recipient->update([
                        'status' => 'failed',
                        'error_message' => 'No phone number found',
                    ]);
                    return;
                }

                // Replace variables in content
                $message = $this->replaceVariables($this->content, $customer);
                
                // اگر تصویر وجود دارد، URL کامل آن را بساز
                $imageUrl = null;
                if ($this->image) {
                    $imageUrl = asset('storage/' . $this->image);
                }
                
                $result = $whatsappService->sendMessage($phone, $message, $imageUrl);
            } elseif ($this->type === 'email') {
                $emailService = app(EmailService::class);
                $email = $customer->email ?? $customer->contacts()->where('type', 'email')->first()?->value;
                
                if (!$email) {
                    $this->recipient->update([
                        'status' => 'failed',
                        'error_message' => 'No email address found',
                    ]);
                    return;
                }

                // Replace variables in content
                $content = $this->replaceVariables($this->content, $customer);
                $subject = $this->subject ? $this->replaceVariables($this->subject, $customer) : 'Campaign Message';
                
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
            Log::error('Campaign message sending failed: ' . $e->getMessage());
            $this->recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    protected function replaceVariables(string $content, $customer): string
    {
        $variables = [
            '{name}' => $customer->name,
            '{company}' => $customer->company_name ?? '',
            '{email}' => $customer->email ?? '',
            '{phone}' => $customer->phone ?? '',
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }
}
