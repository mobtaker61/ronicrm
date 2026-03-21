<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use App\Services\CustomerMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramSaveIncomingMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        $connection = DB::connection();
        try {
            $connection->reconnect();
        } catch (\Throwable) {
            // ignore
        }

        $p = $this->payload;
        $chatId = $p['chat_id'] ?? null;
        $msgId = $p['telegram_message_id'] ?? null;

        if (!$chatId || !$msgId) {
            return;
        }

        if (TelegramMessage::where('chat_id', $chatId)->where('telegram_message_id', $msgId)->exists()) {
            Log::debug('TelegramSaveIncomingMessageJob: duplicate incoming ignored', [
                'chat_id' => $chatId,
                'telegram_message_id' => $msgId,
            ]);
            return;
        }

        $customer = $this->findOrCreateCustomer(
            $chatId,
            $p['from_username'] ?? null,
            $p['from_phone'] ?? null
        );

        $row = TelegramMessage::create([
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

        // Defensive commit for rare orphan PDO transactions in long-lived CLI workers.
        $this->commitOrphanPdoTransactionIfNeeded($connection);

        $persisted = TelegramMessage::whereKey($row->id)->exists();
        Log::info('TelegramSaveIncomingMessageJob: stored incoming', [
            'id' => $row->id,
            'chat_id' => $chatId,
            'telegram_message_id' => $msgId,
            'persisted' => $persisted,
            'tx_level' => $connection->transactionLevel(),
            'in_pdo_tx' => $this->isPdoInTransaction($connection),
        ]);
    }

    protected function isPdoInTransaction(\Illuminate\Database\Connection $connection): bool
    {
        try {
            return $connection->getPdo()->inTransaction();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function commitOrphanPdoTransactionIfNeeded(\Illuminate\Database\Connection $connection): void
    {
        try {
            $pdo = $connection->getPdo();
        } catch (\Throwable) {
            return;
        }

        if (! $pdo->inTransaction()) {
            return;
        }
        if ($connection->transactionLevel() > 0) {
            return;
        }

        try {
            $pdo->commit();
            Log::warning('TelegramSaveIncomingMessageJob: committed orphan PDO transaction');
        } catch (\Throwable) {
            // ignore
        }
    }

    protected function findOrCreateCustomer(string $chatId, ?string $username, ?string $phone = null): ?Customer
    {
        $existing = CustomerMatchService::findExistingByTelegram($chatId, $username, $phone);
        if ($existing) {
            $this->ensureTelegramContact($existing, $chatId);
            return $existing;
        }

        $name = ($username ? trim($username, '@') : null) ?: ('Telegram ' . substr($chatId, -4));
        $customer = Customer::create([
            'name' => $name,
            'type' => 'person',
            'status' => 'lead',
            'source' => 'telegram',
            'created_by' => null,
        ]);
        $this->ensureTelegramContact($customer, $chatId);
        if ($phone) {
            $customer->update(['phone' => $phone]);
            if (!CustomerContact::where('customer_id', $customer->id)->where('type', 'phone')->exists()) {
                $customer->contacts()->create(['type' => 'phone', 'value' => $phone, 'is_primary' => false]);
            }
        }
        return $customer;
    }

    protected function ensureTelegramContact(Customer $customer, string $chatId): void
    {
        $contact = CustomerContact::where('customer_id', $customer->id)->where('type', 'telegram')->first();
        if ($contact) {
            if ($contact->value !== $chatId) {
                $contact->update(['value' => $chatId]);
            }
        } else {
            $customer->contacts()->create([
                'type' => 'telegram',
                'value' => $chatId,
                'is_primary' => true,
            ]);
        }
    }
}
