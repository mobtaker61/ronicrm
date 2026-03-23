<?php

namespace App\Jobs;

use App\Models\CampaignTemplate;
use App\Models\TelegramGroup;
use App\Models\TelegramUserConnection;
use App\Services\MadelineProtoService;
use App\Support\OrganizationContext;
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
        public string $sendId,
        public ?array $groupTitles = null,
        public ?int $organizationId = null
    ) {}

    public function handle(): void
    {
        OrganizationContext::setOrganizationId($this->organizationId ?? OrganizationContext::getOrganizationId());

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
        $imagePath = $tmpl->image ? storage_path('app/public/' . $tmpl->image) : null;

        $service = new MadelineProtoService($conn);
        $total = count($this->groupIds);
        $sent = 0;
        $failed = 0;
        $results = [];
        $titles = $this->groupTitles ?? [];

        $this->setProgress('running', 0, $sent, $failed, $results);
        $service->start();

        $dbGroups = TelegramGroup::where('telegram_user_connection_id', $conn->id)
            ->whereIn('telegram_group_id', $this->groupIds)
            ->get()
            ->keyBy('telegram_group_id');

        foreach ($this->groupIds as $i => $groupId) {
            $group = $dbGroups->get($groupId);
            $langCode = $group?->language;
            $text = $tmpl->getContentForLanguage($langCode);
            $r = $service->sendGroupMessage($groupId, $text, $imagePath);
            $title = $titles[$groupId] ?? null;
            if ($r['success']) {
                $sent++;
                $results[] = ['group_id' => $groupId, 'status' => 'sent'];
                $tg = TelegramGroup::where('telegram_user_connection_id', $conn->id)
                    ->where('telegram_group_id', $groupId)
                    ->first();
                if ($tg) {
                    $tg->markCanPost();
                } else {
                    TelegramGroup::findOrCreateForConnection($conn->id, $groupId, $title, null);
                }
            } else {
                $failed++;
                $err = $r['error'] ?? '';
                $results[] = ['group_id' => $groupId, 'status' => 'failed', 'error' => $err];
                if (static::isNonPostableError($err)) {
                    $tg = TelegramGroup::findOrCreateForConnection($conn->id, $groupId, $title, null);
                    $tg->markCannotPost($err);
                }
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

    protected static function isNonPostableError(string $error): bool
    {
        $nonPostable = [
            'CHAT_ADMIN_REQUIRED',
            'CHAT_WRITE_FORBIDDEN',
            'CHANNEL_PRIVATE',
            'USER_BANNED_IN_CHANNEL',
            'PEER_ID_INVALID',
        ];
        foreach ($nonPostable as $code) {
            if (str_contains($error, $code)) {
                return true;
            }
        }
        return false;
    }
}
