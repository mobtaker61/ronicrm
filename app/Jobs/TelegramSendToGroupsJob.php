<?php

namespace App\Jobs;

use App\Models\CampaignTemplate;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TelegramSendToGroupsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public array $groupIds,
        public int $templateId,
        public string $sendId
    ) {}

    public function handle(): void
    {
        $conn = TelegramUserConnection::getActive();
        if (!$conn || !$conn->isConnected()) {
            $this->setProgress('error', 0, 0, 0, [], 'No active Telegram connection.');
            return;
        }
        $tmpl = CampaignTemplate::find($this->templateId);
        if (!$tmpl || $tmpl->type !== 'telegram') {
            $this->setProgress('error', 0, 0, 0, [], 'Template not found or invalid.');
            return;
        }
        $text = $tmpl->content;
        $imagePath = $tmpl->image ? storage_path('app/public/' . $tmpl->image) : null;

        $service = new MadelineProtoService($conn);
        $total = count($this->groupIds);
        $sent = 0;
        $failed = 0;
        $results = [];

        $this->setProgress('running', 0, $sent, $failed, $results);
        $service->start();

        foreach ($this->groupIds as $i => $groupId) {
            $r = $service->sendGroupMessage($groupId, $text, $imagePath);
            if ($r['success']) {
                $sent++;
                $results[] = ['group_id' => $groupId, 'status' => 'sent'];
            } else {
                $failed++;
                $results[] = ['group_id' => $groupId, 'status' => 'failed', 'error' => $r['error'] ?? ''];
            }
            $this->setProgress('running', $i + 1, $sent, $failed, $results);
            sleep(rand(3, 6));
        }

        $this->setProgress('completed', $total, $sent, $failed, $results);
    }

    protected function setProgress(string $status, int $processed, int $sent, int $failed, array $results, ?string $error = null): void
    {
        $key = 'telegram_send_groups_' . $this->sendId;
        $data = [
            'status' => $status,
            'processed' => $processed,
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
        ];
        if ($error !== null) {
            $data['error'] = $error;
        }
        Cache::put($key, $data, now()->addHours(24));
    }
}
