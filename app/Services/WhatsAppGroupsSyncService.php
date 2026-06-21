<?php

namespace App\Services;

use App\Models\TelegramGroup;
use App\Support\OrganizationContext;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\Log;

class WhatsAppGroupsSyncService
{
    public function syncFromApi(?int $organizationId = null): int
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId || ! WhatsAppSettings::isReady($organizationId)) {
            return 0;
        }

        $settings = WhatsAppSettings::get($organizationId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '') {
            return 0;
        }

        $api = app(WhatsAppYarApiService::class)->forOrganization((int) $organizationId);
        $payload = $api->listGroups($sessionId);
        $groups = $this->unwrapList($payload, ['groups', 'data', 'items']);
        $synced = 0;

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupId = (string) ($group['id'] ?? $group['groupId'] ?? $group['jid'] ?? '');
            if ($groupId === '' || ! WhatsAppYarApiService::isGroupChatId($groupId)) {
                continue;
            }

            $existing = TelegramGroup::withoutGlobalScope('organization')
                ->where('organization_id', (int) $organizationId)
                ->where('channel', 'whatsapp')
                ->where('telegram_group_id', $groupId)
                ->first();

            $attrs = [
                'telegram_user_connection_id' => null,
                'title' => (string) ($group['name'] ?? $group['subject'] ?? $group['title'] ?? $groupId),
                'type' => 'group',
                'member_count' => isset($group['participantsCount'])
                    ? (int) $group['participantsCount']
                    : (isset($group['size']) ? (int) $group['size'] : null),
                'description' => $group['description'] ?? null,
                'can_post' => true,
                'is_active' => true,
                'last_synced_at' => now(),
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                TelegramGroup::withoutGlobalScope('organization')->create(array_merge($attrs, [
                    'organization_id' => (int) $organizationId,
                    'channel' => 'whatsapp',
                    'telegram_group_id' => $groupId,
                    'at_inbox' => false,
                ]));
            }
            $synced++;
        }

        Log::info('WhatsApp groups synced', ['organization_id' => $organizationId, 'count' => $synced]);

        return $synced;
    }

    public function leaveGroup(TelegramGroup $group, ?int $organizationId = null): void
    {
        $organizationId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $organizationId || ($group->channel ?? '') !== 'whatsapp') {
            throw new \RuntimeException('Invalid WhatsApp group.');
        }

        if ((int) $group->organization_id !== (int) $organizationId) {
            throw new \RuntimeException('Unauthorized group.');
        }

        $settings = WhatsAppSettings::get($organizationId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new \RuntimeException('WhatsApp session is not configured.');
        }

        $api = app(WhatsAppYarApiService::class)->forOrganization((int) $organizationId);
        $api->leaveGroup($sessionId, (string) $group->telegram_group_id);

        $group->update([
            'is_active' => false,
            'last_synced_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    protected function unwrapList(array $payload, array $keys): array
    {
        if (array_is_list($payload)) {
            return $payload;
        }

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : array_values($payload[$key]);
            }
        }

        return [];
    }
}
