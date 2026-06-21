<?php

namespace App\Services;

use App\Models\Organization;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Facades\Log;

class WhatsAppSessionManager
{
    /**
     * Ensure a usable remote session exists; recreate when missing or broken.
     *
     * @return array{session_id: string, recreated: bool, previous_session_id: string|null}
     */
    public function resolveOrCreateSession(WhatsAppYarApiService $api, Organization $org): array
    {
        $orgId = (int) $org->id;
        $sessionName = WhatsAppSettings::sessionNameForOrganization($org);
        $settings = WhatsAppSettings::get($orgId);
        $sessionId = trim((string) ($settings['session_id'] ?? ''));
        $previousSessionId = $sessionId !== '' ? $sessionId : null;
        $recreated = false;

        if ($sessionId !== '') {
            $needsRecreate = false;

            try {
                $session = $api->getSession($sessionId);
                $status = WhatsAppYarApiService::normalizeSessionStatus($session);
                if (WhatsAppYarApiService::isSessionBrokenStatus($status)) {
                    $needsRecreate = true;
                    Log::info('WhatsApp session is broken, will recreate', [
                        'organization_id' => $orgId,
                        'session_id' => $sessionId,
                        'status' => $status,
                    ]);
                }
            } catch (\Throwable $e) {
                if (WhatsAppYarApiService::isSessionNotFoundError($e)) {
                    $needsRecreate = true;
                    Log::info('WhatsApp session not found remotely, will recreate', [
                        'organization_id' => $orgId,
                        'session_id' => $sessionId,
                    ]);
                } else {
                    throw $e;
                }
            }

            if ($needsRecreate) {
                $this->discardRemoteSession($api, $sessionId);
                $this->clearLocalSession($orgId);
                $sessionId = '';
                $recreated = true;
            }
        }

        if ($sessionId === '') {
            $existingId = $this->findRemoteSessionIdByName($api, $sessionName);
            if ($existingId !== null) {
                try {
                    $session = $api->getSession($existingId);
                    $status = WhatsAppYarApiService::normalizeSessionStatus($session);
                    if (! WhatsAppYarApiService::isSessionBrokenStatus($status)) {
                        $sessionId = $existingId;
                    } else {
                        $this->discardRemoteSession($api, $existingId);
                    }
                } catch (\Throwable $e) {
                    if (WhatsAppYarApiService::isSessionNotFoundError($e)) {
                        $sessionId = '';
                    } else {
                        throw $e;
                    }
                }
            }
        }

        if ($sessionId === '') {
            $created = $api->createSession($sessionName);
            $sessionId = (string) ($created['id'] ?? $created['data']['id'] ?? '');
            if ($sessionId === '') {
                throw new \RuntimeException('Session ID missing from WhatsAppYar response.');
            }
            $recreated = true;
        }

        WhatsAppSettings::set(array_merge([
            'session_id' => $sessionId,
            'session_name' => $sessionName,
            'status' => WhatsAppYarApiService::normalizeSessionStatus($api->getSession($sessionId)) ?: 'created',
        ], $recreated ? [
            'enabled' => false,
            'line_phone' => '',
            'webhook_id' => '',
            'webhook_secret' => '',
            'webhook_pending' => false,
            'webhook_error' => '',
        ] : []), $orgId);

        if ($recreated && $previousSessionId !== null && $previousSessionId !== $sessionId) {
            Log::info('WhatsApp session recreated', [
                'organization_id' => $orgId,
                'previous_session_id' => $previousSessionId,
                'session_id' => $sessionId,
            ]);
        }

        return [
            'session_id' => $sessionId,
            'recreated' => $recreated,
            'previous_session_id' => $previousSessionId,
        ];
    }

    public function clearLocalSession(int $organizationId): void
    {
        WhatsAppSettings::set([
            'enabled' => false,
            'session_id' => '',
            'session_name' => '',
            'line_phone' => '',
            'webhook_id' => '',
            'webhook_secret' => '',
            'webhook_pending' => false,
            'webhook_error' => '',
            'status' => 'disconnected',
        ], $organizationId);
    }

    public function discardRemoteSession(WhatsAppYarApiService $api, string $sessionId): void
    {
        foreach (['forceKillSession', 'stopSession', 'deleteSession'] as $method) {
            try {
                $api->{$method}($sessionId);
            } catch (\Throwable $e) {
                if (! WhatsAppYarApiService::isSessionNotFoundError($e)) {
                    Log::debug('WhatsApp discard session step failed', [
                        'method' => $method,
                        'session_id' => $sessionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function findRemoteSessionIdByName(WhatsAppYarApiService $api, string $sessionName): ?string
    {
        try {
            $payload = $api->listSessions();
            $sessions = $this->unwrapList($payload, ['sessions', 'data', 'items']);

            foreach ($sessions as $session) {
                if (! is_array($session)) {
                    continue;
                }
                $name = (string) ($session['name'] ?? '');
                if ($name === $sessionName) {
                    $id = (string) ($session['id'] ?? '');
                    if ($id !== '') {
                        return $id;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp listSessions failed during resolve', ['error' => $e->getMessage()]);
        }

        return null;
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
