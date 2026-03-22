<?php

namespace App\Jobs;

use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramGroup;
use App\Models\TelegramMessage;
use App\Models\TelegramUserConnection;
use App\Services\CustomerMatchService;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramCrawlJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string $groupId,
        public int $limit,
        public string $messageText,
        public string $crawlId,
        public ?int $templateId = null
    ) {}

    public function handle(): void
    {
        Log::info('TelegramCrawlJob started', ['crawl_id' => $this->crawlId, 'group_id' => $this->groupId]);

        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            $this->setProgress('error', 0, 0, 0, 'No active Telegram connection.');
            Log::warning('TelegramCrawlJob: No active connection');
            return;
        }
        $service = new MadelineProtoService($conn);
        $processed = 0;
        $sent = 0;
        $skipped = 0;
        try {
            $this->setProgress('running', 0, 0, 0, null, 'fetching_messages', null);
            Log::info('TelegramCrawlJob: calling start()');
            $service->start();
            Log::info('TelegramCrawlJob: start() done, fetching messages');
            $data = $service->getGroupMessages($this->groupId, $this->limit);
            $messages = $data['valid'] ?? [];
            $allPreview = $data['all'] ?? [];
            Log::info('TelegramCrawlJob: got messages', ['valid' => count($messages), 'total' => count($allPreview)]);

            $authors = [];
            foreach ($messages as $msg) {
                $uid = $this->normalizeUserId($msg['from_id'] ?? null);
                if ($uid && !isset($authors[$uid])) {
                    $authors[$uid] = $msg;
                }
            }
            $total = count($authors);
            $this->setProgress('running', 0, 0, 0, null, 'identifying_authors', $total, count($allPreview), $allPreview);
            $this->setProgress('running', 0, 0, 0, null, 'sending_messages', $total, null, $allPreview);
            Log::info('TelegramCrawlJob: sending to authors', ['total' => $total]);
            $imagePath = null;
            $messageTextToUse = $this->messageText;
            $groupLang = null;
            $group = TelegramGroup::where('telegram_user_connection_id', $conn->id)
                ->where('telegram_group_id', $this->groupId)
                ->first();
            if ($group) {
                $groupLang = $group->language;
            }
            if ($this->templateId) {
                $tmpl = CampaignTemplate::find($this->templateId);
                if ($tmpl) {
                    if ($tmpl->image) {
                        $imagePath = storage_path('app/public/' . $tmpl->image);
                    }
                    $messageTextToUse = $tmpl->getContentForLanguage($groupLang) ?: $this->messageText;
                }
            }
            $authorsSent = [];
            $idx = 0;
            foreach ($authors as $userId => $msg) {
                $userId = $this->normalizeUserId($userId) ?? (string) $userId;
                $idx++;
                $this->setProgress('running', $idx, $sent, $skipped, null, 'sending_messages', $total, null, null, $authorsSent);
                Log::info('TelegramCrawlJob: sending to user', ['user_id' => $userId, 'idx' => $idx, 'total' => $total]);
                if ($this->alreadyMessaged($userId)) {
                    $skipped++;
                    $authorsSent[] = ['user_id' => $userId, 'status' => 'skipped'];
                    continue;
                }
                $sentResult = $service->sendPrivateMessage($userId, $messageTextToUse, $imagePath);
                if ($sentResult['success']) {
                    $this->createCustomerAndSaveMessage($userId, $msg, $conn->id, $messageTextToUse);
                    $sent++;
                    $authorsSent[] = ['user_id' => $userId, 'status' => 'sent'];
                } else {
                    $authorsSent[] = ['user_id' => $userId, 'status' => 'failed', 'error' => $sentResult['error'] ?? ''];
                }
                sleep(rand(4, 7));
            }
            $this->setProgress('completed', $total, $sent, $skipped, null, 'completed', $total, null, null, $authorsSent);
        } catch (\Throwable $e) {
            Log::error('TelegramCrawlJob error: ' . $e->getMessage());
            $this->setProgress('error', $processed, $sent, $skipped, $e->getMessage(), 'error', null);
        }
    }

    protected function normalizeUserId($fromId): ?string
    {
        if ($fromId === null) return null;
        if (is_numeric($fromId)) return (string) (int) $fromId;
        if (is_array($fromId)) {
            $uid = $fromId['user_id'] ?? $fromId['id'] ?? null;
            return $uid !== null ? (string) (int) $uid : null;
        }
        return is_string($fromId) ? preg_replace('/\D/', '', $fromId) ?: null : null;
    }

    protected function alreadyMessaged(string $userId): bool
    {
        $userId = (string) $userId;
        if ($userId === '') return false;
        // Direct check: have we ever sent to this chat_id?
        if (TelegramMessage::where('chat_id', $userId)->where('direction', 'outgoing')->exists()) {
            return true;
        }
        // Also check via contact -> customer (belt and suspenders)
        $contact = CustomerContact::where('type', 'telegram')->where('value', $userId)->first();
        if (!$contact) return false;
        return TelegramMessage::where('customer_id', $contact->customer_id)
            ->where('direction', 'outgoing')->exists();
    }

    protected function createCustomerAndSaveMessage(string $userId, array $msg, ?int $connId, ?string $messageSent = null): void
    {
        $customer = CustomerMatchService::findExistingByTelegram($userId, null, null);
        if ($customer) {
            $this->ensureTelegramContact($customer, $userId);
        } else {
            $customer = Customer::create([
                'name' => 'Telegram User ' . substr($userId, -4),
                'type' => 'person',
                'status' => 'lead',
                'source' => 'crawl',
                'created_by' => null,
            ]);
            $customer->contacts()->create([
                'type' => 'telegram',
                'value' => $userId,
                'is_primary' => true,
            ]);
        }
        TelegramMessage::create([
            'chat_id' => $userId,
            'message' => $messageSent ?? $this->messageText,
            'message_type' => 'text',
            'customer_id' => $customer->id,
            'direction' => 'outgoing',
            'status' => 'sent',
        ]);
    }

    protected function ensureTelegramContact(Customer $customer, string $chatId): void
    {
        $contact = CustomerContact::where('customer_id', $customer->id)->where('type', 'telegram')->first();
        if ($contact && $contact->value !== $chatId) {
            $contact->update(['value' => $chatId]);
        }
    }

    protected function setProgress(
        string $status,
        int $processed,
        int $sent,
        int $skipped,
        ?string $error = null,
        ?string $phase = null,
        ?int $total = null,
        ?int $messagesScanned = null,
        ?array $messagesPreview = null,
        ?array $authorsSent = null
    ): void {
        $key = 'telegram_crawl_' . $this->crawlId;
        $data = [
            'status' => $status,
            'processed' => $processed,
            'sent' => $sent,
            'skipped' => $skipped,
            'error' => $error,
        ];
        if ($phase !== null) {
            $data['phase'] = $phase;
        }
        if ($total !== null) {
            $data['total'] = $total;
        }
        if ($messagesScanned !== null) {
            $data['messages_scanned'] = $messagesScanned;
        }
        if ($messagesPreview !== null) {
            $data['messages_preview'] = $messagesPreview;
        } else {
            $existing = Cache::get($key);
            if (is_array($existing) && !empty($existing['messages_preview'])) {
                $data['messages_preview'] = $existing['messages_preview'];
            }
        }
        if ($authorsSent !== null) {
            $data['authors_sent'] = $authorsSent;
        } else {
            $existing = Cache::get($key);
            if (is_array($existing) && !empty($existing['authors_sent'])) {
                $data['authors_sent'] = $existing['authors_sent'];
            }
        }
        Cache::put($key, $data, now()->addHours(24));
    }
}
