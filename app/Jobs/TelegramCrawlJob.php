<?php

namespace App\Jobs;

use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\TelegramMessage;
use App\Models\TelegramUserConnection;
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
            $messages = $service->getGroupMessages($this->groupId, $this->limit);
            Log::info('TelegramCrawlJob: got messages', ['count' => count($messages)]);
            $authors = [];
            $msgCount = 0;
            foreach ($messages as $msg) {
                $msgCount++;
                if ($msgCount % 10 === 0) {
                    $this->setProgress('running', 0, 0, 0, null, 'identifying_authors', null, $msgCount);
                }
                $uid = $msg['from_id'] ?? null;
                if ($uid && !isset($authors[$uid])) {
                    $authors[$uid] = $msg;
                }
            }
            $total = count($authors);
            $this->setProgress('running', 0, 0, 0, null, 'identifying_authors', null, count($messages));
            $this->setProgress('running', 0, 0, 0, null, 'sending_messages', $total);
            Log::info('TelegramCrawlJob: sending to authors', ['total' => $total]);
            $idx = 0;
            foreach ($authors as $userId => $msg) {
                $idx++;
                $this->setProgress('running', $idx, $sent, $skipped, null, 'sending_messages', $total);
                Log::info('TelegramCrawlJob: sending to user', ['user_id' => $userId, 'idx' => $idx, 'total' => $total]);
                if ($this->alreadyMessaged($userId)) {
                    $skipped++;
                    continue;
                }
                $sentResult = $service->sendPrivateMessage($userId, $this->messageText);
                if ($sentResult['success']) {
                    $this->createCustomerAndSaveMessage($userId, $msg, $conn->id);
                    $sent++;
                }
                sleep(rand(4, 7));
            }
            $this->setProgress('completed', $total, $sent, $skipped, null, 'completed', $total);
        } catch (\Throwable $e) {
            Log::error('TelegramCrawlJob error: ' . $e->getMessage());
            $this->setProgress('error', $processed, $sent, $skipped, $e->getMessage(), 'error', null);
        }
    }

    protected function alreadyMessaged(string $userId): bool
    {
        $contact = CustomerContact::where('type', 'telegram')->where('value', $userId)->first();
        if (!$contact) {
            return false;
        }
        return TelegramMessage::where('customer_id', $contact->customer_id)
            ->where('direction', 'outgoing')->exists();
    }

    protected function createCustomerAndSaveMessage(string $userId, array $msg, ?int $connId): void
    {
        $contact = CustomerContact::where('type', 'telegram')->where('value', $userId)->first();
        $customer = $contact?->customer;
        if (!$customer) {
            $customer = Customer::create([
                'name' => 'Telegram User ' . substr($userId, -4),
                'type' => 'person',
                'status' => 'lead',
                'source' => 'telegram_group_crawl',
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
            'message' => $this->messageText,
            'message_type' => 'text',
            'customer_id' => $customer->id,
            'direction' => 'outgoing',
            'status' => 'sent',
        ]);
    }

    protected function setProgress(
        string $status,
        int $processed,
        int $sent,
        int $skipped,
        ?string $error = null,
        ?string $phase = null,
        ?int $total = null,
        ?int $messagesScanned = null
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
        Cache::put($key, $data, now()->addHours(24));
    }
}
