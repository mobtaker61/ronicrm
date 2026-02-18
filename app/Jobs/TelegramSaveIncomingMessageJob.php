<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TelegramSaveIncomingMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        $p = $this->payload;
        $chatId = $p['chat_id'] ?? null;
        $msgId = $p['telegram_message_id'] ?? null;

        if (!$chatId || !$msgId) {
            return;
        }

        if (TelegramMessage::where('chat_id', $chatId)->where('telegram_message_id', $msgId)->exists()) {
            return;
        }

        $customer = $this->findOrCreateCustomer($chatId, $p['from_username'] ?? null);

        TelegramMessage::create([
            'telegram_message_id' => $msgId,
            'chat_id' => $chatId,
            'from_username' => $p['from_username'] ?? null,
            'message' => $p['message'] ?? '',
            'message_type' => $p['message_type'] ?? 'text',
            'media_url' => $p['media_url'] ?? null,
            'media_mime_type' => $p['media_mime_type'] ?? null,
            'customer_id' => $customer?->id,
            'direction' => 'incoming',
            'status' => 'received',
            'metadata' => null,
        ]);
    }

    protected function findOrCreateCustomer(string $chatId, ?string $username): ?Customer
    {
        $contact = CustomerContact::where('type', 'telegram')->where('value', $chatId)->first();
        if ($contact?->customer) {
            return $contact->customer;
        }

        $name = ($username ? trim($username, '@') : null) ?: ('Telegram ' . substr($chatId, -4));
        $customer = Customer::create([
            'name' => $name,
            'type' => 'person',
            'status' => 'lead',
            'source' => 'telegram',
            'created_by' => null,
        ]);
        $customer->contacts()->create([
            'type' => 'telegram',
            'value' => $chatId,
            'is_primary' => true,
        ]);

        return $customer;
    }
}
