<?php

namespace App\Services;

use App\Models\TelegramUserConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MadelineProtoService
{
    protected ?TelegramUserConnection $connection = null;

    protected $api = null;

    public function __construct(?TelegramUserConnection $connection = null)
    {
        $this->connection = $connection;
    }

    public function setConnection(TelegramUserConnection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get or create MadelineProto API instance. Runs in async context.
     */
    protected function getApi()
    {
        if ($this->api !== null) {
            return $this->api;
        }
        if (! $this->connection || ! $this->connection->isConnected()) {
            throw new \RuntimeException('No active Telegram user connection.');
        }
        if (! class_exists(\danog\MadelineProto\API::class)) {
            throw new \RuntimeException('MadelineProto is not installed. Run: composer require danog/madelineproto');
        }
        $sessionPath = $this->connection->getSessionPath();
        if (! is_dir($sessionPath) && ! file_exists($sessionPath)) {
            throw new \RuntimeException('Telegram session files not found. Please reconnect via Settings.');
        }
        $apiId = (int) $this->connection->getApiId();
        $apiHash = $this->connection->getApiHash();
        if (! $apiId || ! $apiHash) {
            $apiId = (int) config('services.telegram.api_id');
            $apiHash = config('services.telegram.api_hash');
        }
        $settings = $this->makeMadelineSettings($apiId, $apiHash);
        $this->api = self::withForcedFullMadelineInstance(fn () => new \danog\MadelineProto\API($sessionPath, $settings));
        if ($this->api->getAuthorization() !== \danog\MadelineProto\API::LOGGED_IN) {
            $this->api = null;
            throw new \RuntimeException('Telegram session is not authenticated. Please reconnect via Settings → Telegram.');
        }

        return $this->api;
    }

    /** Max seconds for any MadelineProto operation (prevents hanging requests). */
    protected int $runTimeout = 180;

    /**
     * مسیر فایل نشانگر وقتی `telegram:listen-incoming` در حال اجراست (برای غیرفعال کردن polling).
     */
    public static function daemonListenMarkerPath(TelegramUserConnection $connection): string
    {
        return dirname($connection->getSessionPath()).'/.madeline_listen_daemon_'.$connection->id;
    }

    /**
     * آیا daemon دریافت لحظه‌ای برای این اتصال فعال است؟ در این حورت telegram:fetch-incoming نباید اجرا شود.
     */
    public static function isListenDaemonActive(?TelegramUserConnection $connection = null): bool
    {
        $conn = $connection ?? TelegramUserConnection::getActive();
        if (! $conn) {
            return false;
        }
        $marker = self::daemonListenMarkerPath($conn);
        if (! is_file($marker)) {
            return false;
        }
        $pid = (int) trim((string) @file_get_contents($marker));

        return self::processProbablyRunning($pid);
    }

    /**
     * MadelineProto 8 به‌صورت پیش‌فرض سعی می‌کند کلاینت IPC به سوکت session وصل شود؛ اگر سرور نیمه‌مرده باشد
     * خطای «channel was already closed» و تایم‌اوت ۳۰۰ثانیه‌ای می‌گیرید. این هک رسمی کتابخانه است (مثل MadelineSelfRestart).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withForcedFullMadelineInstance(callable $callback)
    {
        // In web/FPM, forcing full instance needs proc_open/open_basedir freedom and can fail hard.
        // Keep force-full only for CLI contexts.
        if (! app()->runningInConsole()) {
            return $callback();
        }
        if (! config('services.telegram.madeline_force_full_instance', true)) {
            return $callback();
        }

        $had = array_key_exists('MadelineSelfRestart', $_GET);
        $prev = $had ? $_GET['MadelineSelfRestart'] : null;
        $_GET['MadelineSelfRestart'] = '1';
        try {
            return $callback();
        } finally {
            if ($had) {
                $_GET['MadelineSelfRestart'] = $prev;
            } else {
                unset($_GET['MadelineSelfRestart']);
            }
        }
    }

    /**
     * یک session = یک فرآیند مالک. دامون listen و وب/cron نمی‌توانند همزمان همان session را باز کنند.
     *
     * @throws \RuntimeException
     */
    protected function assertSessionNotHeldByListenDaemon(): void
    {
        if (! $this->connection) {
            return;
        }
        if (! self::isListenDaemonActive($this->connection)) {
            return;
        }
        $marker = self::daemonListenMarkerPath($this->connection);
        $pid = (int) trim((string) @file_get_contents($marker));

        throw new \RuntimeException(
            'فرآیند «telegram:listen-incoming» هنوز session را باز نگه داشته (PID '.$pid.'). '.
            'برای ارسال از اینباکس، کراول گروه، یا telegram:fetch-incoming آن را در Supervisor متوقف کنید. '.
            'دریافت لحظه‌ای فقط با همان daemon است؛ ارسال از وب فقط وقتی دامون خاموش است.'
        );
    }

    /**
     * خلاصهٔ زنجیرهٔ exception برای لاگ وقتی getMessage() خالی است.
     */
    public static function exceptionSummary(\Throwable $e): string
    {
        if ($e instanceof \Illuminate\Contracts\Cache\LockTimeoutException) {
            return 'LockTimeoutException: قفل Cache (کلید madeline_session_*) پس از انتظار آزاد نشد — چند عملیات همزمان Madeline یا MADELINE_PROTO_CACHE_LOCK_BLOCK را افزایش دهید.';
        }
        $parts = [];
        $cur = $e;
        $depth = 0;
        while ($cur !== null && $depth < 8) {
            $m = $cur->getMessage();
            $parts[] = get_class($cur).': '.($m !== '' ? $m : '(empty message)');
            $cur = $cur->getPrevious();
            $depth++;
        }

        return implode(' ← ', $parts);
    }

    protected static function processProbablyRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill') && extension_loaded('posix')) {
            return @posix_kill($pid, 0);
        }
        if (PHP_OS_FAMILY === 'Linux' && is_dir("/proc/$pid")) {
            return true;
        }

        return false;
    }

    /**
     * Run async closure and return result (blocking).
     * Uses explicit EventLoop::run() so the event loop processes async I/O correctly in web context.
     * Includes a safety timeout to prevent indefinite blocking.
     */
    protected function run(callable $callback)
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            throw new \RuntimeException('MadelineProto is not installed. Run: composer require danog/madelineproto');
        }
        $this->runTimeout = max(60, (int) config('services.telegram.madeline_run_timeout', 300));
        $this->assertSessionNotHeldByListenDaemon();

        $connId = $this->connection?->id ?? 0;
        $lockKey = 'madeline_session_'.$connId;
        $lockTtl = max(120, (int) config('services.telegram.madeline_cache_lock_ttl', 600));
        $blockSeconds = max(30, (int) config('services.telegram.madeline_cache_lock_block', 420));
        $lock = Cache::lock($lockKey, $lockTtl);
        try {
            // در Laravel block() یا true برمی‌گرداند یا LockTimeoutException می‌اندازد (هرگز false نیست).
            $lock->block($blockSeconds);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw new \RuntimeException(
                'قفل session تلگرام پس از '.$blockSeconds.' ثانیه آزاد نشد (احتمالاً ارسال/همگام‌سازی دیگری در حال اجراست). بعداً تلاش کنید یا MADELINE_PROTO_CACHE_LOCK_BLOCK را در .env بزرگ‌تر کنید.',
                0,
                $e
            );
        }
        try {
            return $this->runWithLock($callback);
        } finally {
            $lock->release();
        }
    }

    protected function runWithLock(callable $callback)
    {
        $result = null;
        $error = null;
        $timedOut = false;
        $timeoutSeconds = $this->runTimeout;
        $timeoutId = \Revolt\EventLoop::delay($timeoutSeconds, function () use (&$timedOut, &$error, $timeoutSeconds) {
            $timedOut = true;
            if ($error === null) {
                $error = new \RuntimeException(
                    "MadelineProto operation timed out after {$timeoutSeconds}s. ".
                    'Check server clock sync (NTP) and madelineproto.log.'
                );
            }
            \Revolt\EventLoop::getDriver()->stop();
        });
        \Revolt\EventLoop::queue(function () use ($callback, &$result, &$error, $timeoutId) {
            try {
                $future = \Amp\async($callback);
                $result = $future->await();
            } catch (\Throwable $e) {
                $error = $e;
            } finally {
                \Revolt\EventLoop::cancel($timeoutId);
                \Revolt\EventLoop::getDriver()->stop();
            }
        });
        \Revolt\EventLoop::run();
        if ($timedOut && $error === null) {
            $error = new \RuntimeException(
                "MadelineProto operation timed out after {$timeoutSeconds}s. ".
                'Check server clock sync (NTP) and madelineproto.log.'
            );
        }
        if ($error !== null) {
            $msg = $error->getMessage();
            if ($msg === '') {
                $msg = self::exceptionSummary($error);
            }
            $cls = get_class($error);
            Log::error('MadelineProto run() error', [
                'class' => $cls,
                'message' => $msg,
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
            ]);
            $wrap = new \RuntimeException("MadelineProto error: {$cls}: {$msg}", (int) $error->getCode(), $error);
            throw $wrap;
        }

        return $result;
    }

    protected function makeMadelineSettings(int $apiId, string $apiHash): \danog\MadelineProto\Settings
    {
        $settings = new \danog\MadelineProto\Settings;
        $settings->getAppInfo()->setApiId($apiId)->setApiHash($apiHash);
        $settings->getLogger()
            ->setType(\danog\MadelineProto\Logger::LOGGER_FILE)
            ->setExtra(storage_path('logs/madelineproto.log'))
            // Keep only warnings/errors in production to avoid huge update-feed logs.
            ->setLevel(\danog\MadelineProto\Logger::LEVEL_WARNING);

        return $settings;
    }

    /**
     * Start MadelineProto and ensure logged in.
     */
    public function start(): void
    {
        Log::info('MadelineProtoService::start() begin');
        $this->run(function () {
            $api = $this->getApi();
            $api->start();
            $this->connection?->update(['last_used_at' => now()]);
        });
        Log::info('MadelineProtoService::start() end');
    }

    /**
     * Get list of dialogs (chats, groups, channels).
     *
     * @return array Array of [id => string, title => string, type => string]
     */
    public function getDialogs(): array
    {
        $result = $this->run(function () {
            $api = $this->getApi();
            $api->start();

            foreach (['getDialogsFromMessagesApi', 'getDialogsFromDialogIds', 'getDialogsFromFullDialogs'] as $method) {
                try {
                    $out = $this->{$method}($api);
                    if ($method !== 'getDialogsFromFullDialogs') {
                        Log::info("MadelineProto getDialogs: used {$method}");
                    }
                    return $out;
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    if (str_contains($msg, 'Undefined array key') || str_contains($msg, 'Undefined index')) {
                        Log::info("MadelineProto {$method} entity key bug, trying next", ['error' => substr($msg, 0, 80)]);
                        continue;
                    }
                    throw $e;
                }
            }
            return [];
        });

        return \is_array($result) ? $result : [];
    }

    /**
     * Fetch dialogs using low-level messages.getDialogs (avoids entity cache bug).
     */
    protected function getDialogsFromMessagesApi($api): array
    {
        $allChats = [];
        $offsetPeer = ['_' => 'inputPeerEmpty'];
        $offsetId = 0;
        $offsetDate = 0;

        for ($i = 0; $i < 20; $i++) {
            $resp = $api->messages->getDialogs(
                limit: 100,
                offset_peer: $offsetPeer,
                offset_id: $offsetId,
                offset_date: $offsetDate,
            );
            $dialogs = $resp['dialogs'] ?? [];
            $chats = $resp['chats'] ?? [];
            $users = $resp['users'] ?? [];

            $chatMap = [];
            foreach ($chats as $c) {
                $key = $this->getEntityMapKey($c);
                if ($key !== null) {
                    $chatMap[$key] = $c;
                }
            }
            foreach ($users as $u) {
                $key = $this->getEntityMapKey($u);
                if ($key !== null) {
                    $chatMap[$key] = $u;
                }
            }

            $lastPeer = null;
            foreach ($dialogs as $d) {
                $peer = $d['peer'] ?? null;
                if (! $peer) {
                    continue;
                }
                $idStr = $this->getPeerIdFromDialogPeer($peer);
                if ($idStr === null || ! str_starts_with($idStr, '-')) {
                    continue;
                }
                $entity = $chatMap[$idStr] ?? null;
                $title = $this->extractTitleFromEntity($entity);
                $type = $this->extractTypeFromEntity($entity);
                if (! in_array($type, ['group', 'supergroup', 'channel'], true)) {
                    continue;
                }
                $allChats[$idStr] = [
                    'id' => $idStr,
                    'title' => $title ?: 'Unknown',
                    'type' => $type === 'chat' ? 'group' : $type,
                ];
                $lastPeer = $peer;
            }

            if (empty($dialogs) || count($dialogs) < 100) {
                break;
            }
            $last = end($dialogs);
            $offsetId = $last['top_message'] ?? 0;
            $offsetDate = $last['read_inbox_max_id'] ?? 0;
            $offsetPeer = $lastPeer ?? $last['peer'] ?? $offsetPeer;
        }

        usort($allChats, fn ($a, $b) => strcasecmp($a['title'], $b['title']));

        return array_values($allChats);
    }

    protected function getPeerIdFromDialogPeer(array $peer): ?string
    {
        $t = $peer['_'] ?? '';
        if (str_contains($t, 'Channel')) {
            return '-100'.(string) ($peer['channel_id'] ?? '');
        }
        if (str_contains($t, 'Chat')) {
            return '-'.(string) ($peer['chat_id'] ?? '');
        }
        if (str_contains($t, 'User')) {
            return (string) ($peer['user_id'] ?? '');
        }
        return null;
    }

    protected function getEntityMapKey(array $entity): ?string
    {
        $t = $entity['_'] ?? '';
        $id = $entity['id'] ?? null;
        if ($id === null) {
            return null;
        }
        if (str_contains($t, 'Channel')) {
            return '-100'.(string) $id;
        }
        if (str_contains($t, 'Chat')) {
            return '-'.(string) $id;
        }
        if (str_contains($t, 'User')) {
            return (string) $id;
        }
        return null;
    }

    protected function extractTitleFromEntity(?array $entity): string
    {
        if (! $entity) {
            return '';
        }
        return $entity['title'] ?? trim(($entity['first_name'] ?? '').' '.($entity['last_name'] ?? '')) ?: '';
    }

    protected function extractTypeFromEntity(?array $entity): string
    {
        if (! $entity) {
            return 'user';
        }
        $t = $entity['_'] ?? '';
        if (str_contains($t, 'Channel')) {
            return (($entity['broadcast'] ?? false) && ! ($entity['megagroup'] ?? false)) ? 'channel' : 'supergroup';
        }
        if (str_contains($t, 'Chat')) {
            return 'group';
        }
        return 'user';
    }

    /**
     * Fetch dialogs using getFullDialogs (preferred, more info).
     */
    protected function getDialogsFromFullDialogs($api): array
    {
        $dialogs = $api->getFullDialogs();
        $out = [];
        foreach ($dialogs as $dialog) {
            $peer = $dialog['peer'] ?? null;
            if (! $peer) {
                continue;
            }
            $id = $api->getId($peer);
            if (! str_starts_with((string) $id, '-')) {
                continue;
            }
            $title = $this->extractDialogTitle($dialog, $api);
            $type = $this->getPeerType($peer, $api);
            $out[] = [
                'id' => (string) $id,
                'title' => $title,
                'type' => $type,
            ];
        }
        return $out;
    }

    /**
     * Fallback: fetch dialogs using getDialogIds + getInfo (when getFullDialogs has entity key bug).
     */
    protected function getDialogsFromDialogIds($api): array
    {
        $peerIds = $api->getDialogIds();
        $out = [];
        foreach ($peerIds as $peer) {
            try {
                $id = $api->getId($peer);
                $idStr = (string) $id;
                if (! str_starts_with($idStr, '-')) {
                    continue;
                }
                $info = $api->getInfo($peer);
                $type = $info['type'] ?? 'user';
                if (! in_array($type, ['chat', 'group', 'supergroup', 'channel'], true)) {
                    continue;
                }
                $title = $info['Chat']['title'] ?? $info['User']['first_name'] ?? 'Unknown';
                $out[] = [
                    'id' => $idStr,
                    'title' => $title,
                    'type' => $type === 'chat' ? 'group' : $type,
                ];
            } catch (\Throwable $e) {
                Log::debug('MadelineProto skip dialog', ['error' => $e->getMessage()]);
            }
        }
        return $out;
    }

    protected function extractDialogTitle(array $dialog, $api): string
    {
        $peer = $dialog['peer'] ?? null;
        if (! $peer) {
            return 'Unknown';
        }
        if (isset($peer['title'])) {
            return $peer['title'];
        }
        if (isset($peer['first_name'])) {
            return trim(($peer['first_name'] ?? '').' '.($peer['last_name'] ?? ''));
        }
        if (isset($dialog['name'])) {
            return $dialog['name'];
        }
        try {
            $entity = $api->getInfo($peer);

            return $entity['User']['first_name'] ?? $entity['Chat']['title'] ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    /**
     * Determine peer type from Telegram API response: group, supergroup, channel, or user.
     * Uses getInfo when api is available to get the correct type (MadelineProto returns chat/supergroup/channel).
     */
    protected function getPeerType($peer, $api = null): string
    {
        if ($api) {
            try {
                $info = $api->getInfo($peer);
                $t = $info['type'] ?? '';
                if (in_array($t, ['chat', 'group', 'supergroup', 'channel'], true)) {
                    return $t === 'chat' ? 'group' : $t;
                }

                return 'user';
            } catch (\Throwable $e) {
                Log::warning('MadelineProto getPeerType getInfo failed: '.$e->getMessage());
            }
        }
        $type = $peer['_'] ?? '';
        if (str_contains($type, 'Channel')) {
            return isset($peer['megagroup']) && $peer['megagroup'] ? 'supergroup' : 'channel';
        }
        if (str_contains($type, 'Chat')) {
            return 'group';
        }

        return 'user';
    }

    /**
     * Get messages from a chat/group. Returns all messages with metadata for display;
     * valid messages (with user author) are usable for sending.
     *
     * @param  string  $peerId  Chat/group ID (e.g. -1001234567890)
     * @param  int  $limit  Number of messages to fetch
     * @return array{valid: array, all: array} valid=for authors, all=every message for preview
     */
    public function getGroupMessages(string $peerId, int $limit = 50): array
    {
        return $this->run(function () use ($peerId, $limit) {
            $api = $this->getApi();
            $api->start();
            $fetchPeerId = $peerId;
            $info = $api->getInfo($peerId);
            $isChannel = ($info['Chat']['broadcast'] ?? false) && ! ($info['Chat']['megagroup'] ?? false);
            if ($isChannel) {
                $full = $api->getFullInfo($peerId);
                $linkedChatId = $full['linked_chat_id'] ?? $full['full_chat']['linked_chat_id'] ?? null;
                if ($linkedChatId) {
                    $fetchPeerId = '-100'.$linkedChatId;
                    Log::info('MadelineProto: channel has discussion group, fetching from', ['linked' => $fetchPeerId]);
                }
            }
            $messages = $api->messages->getHistory(peer: $fetchPeerId, limit: min($limit, 100));
            $raw = $messages['messages'] ?? [];
            $valid = [];
            $all = [];
            $channelId = ltrim(preg_replace('/^-100/', '', (string) $fetchPeerId), '-') ?: (string) $fetchPeerId;
            foreach ($raw as $msg) {
                $msgId = $msg['id'] ?? null;
                $fromIdRaw = $msg['from_id'] ?? null;
                $fromType = is_array($fromIdRaw) ? ($fromIdRaw['_'] ?? 'empty') : (is_numeric($fromIdRaw) ? 'user_id' : 'empty');
                $fromId = $this->extractUserId($fromIdRaw);
                if (! $fromId && ! empty($msg['fwd_from']['from_id'])) {
                    $fromId = $this->extractUserId($msg['fwd_from']['from_id']);
                    $fromType = $fromId ? 'fwd_from' : $fromType;
                }
                $text = mb_substr($msg['message'] ?? '', 0, 100);
                $link = $this->makeMessageLink($peerId, $msgId, $channelId);
                $all[] = [
                    'id' => $msgId,
                    'from_type' => $fromType,
                    'from_id' => $fromId,
                    'text' => $text,
                    'link' => $link,
                    'raw_json' => json_encode($msg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ];
                if ($fromId && $fromType !== 'peerChannel') {
                    $valid[] = [
                        'id' => $msgId,
                        'from_id' => $fromId,
                        'text' => $msg['message'] ?? '',
                        'date' => $msg['date'] ?? null,
                    ];
                }
            }

            return ['valid' => $valid, 'all' => $all];
        });
    }

    protected function makeMessageLink(string $peerId, ?int $msgId, string $channelId): ?string
    {
        if ($msgId === null) {
            return null;
        }

        return "https://t.me/c/{$channelId}/{$msgId}";
    }

    /**
     * Get private chat (DM) history with a user.
     *
     * @param  string  $userId  Telegram user ID (peer for PM)
     * @param  int  $limit  Max messages to fetch
     * @param  int|null  $minId  Only return messages with id > min_id (for incremental fetch)
     * @return array{ messages: array, users: array } messages=raw messages, users=resolved User entities
     */
    public function getPrivateChatHistory(string $userId, int $limit = 50, ?int $minId = null): array
    {
        try {
            $result = $this->run(function () use ($userId, $limit, $minId) {
                $api = $this->getApi();
                $api->start();
                $messages = $api->messages->getHistory(
                    peer: (int) $userId,
                    limit: min($limit, 100),
                    offset_id: 0,
                    offset_date: 0,
                    add_offset: 0,
                    max_id: 0,
                    min_id: $minId ?? 0,
                    hash: [0],
                );
                $raw = $messages['messages'] ?? [];
                $users = $messages['users'] ?? [];

                return ['messages' => $raw, 'users' => $users];
            });

            if (! \is_array($result)) {
                return ['messages' => [], 'users' => []];
            }

            return [
                'messages' => \is_array($result['messages'] ?? null) ? $result['messages'] : [],
                'users' => \is_array($result['users'] ?? null) ? $result['users'] : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('MadelineProto getPrivateChatHistory failed', [
                'user_id' => $userId,
                'detail' => self::exceptionSummary($e),
                'class' => get_class($e),
            ]);

            return ['messages' => [], 'users' => []];
        }
    }

    /**
     * Get user info (name, username, phone if available) from Telegram.
     *
     * @param  string  $userId  Telegram user ID
     * @return array{ first_name?: string, last_name?: string, username?: string, phone?: string }
     */
    public function getTelegramUserInfo(string $userId): array
    {
        try {
            $result = $this->run(function () use ($userId) {
                $api = $this->getApi();
                $api->start();
                $info = $api->getInfo((int) $userId);
                $user = $info['User'] ?? null;
                if (! $user || ! \is_array($user)) {
                    return [];
                }

                return [
                    'first_name' => $user['first_name'] ?? '',
                    'last_name' => $user['last_name'] ?? '',
                    'username' => $user['username'] ?? null,
                    'phone' => $user['phone'] ?? null,
                ];
            });

            return \is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            Log::warning('MadelineProto getTelegramUserInfo failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get full user info from Telegram (name, username, phone, profile photo).
     * Uses getFullInfo for complete data. Downloads profile photo if available.
     *
     * @param  string  $userId  Telegram user ID
     * @return array{ first_name?: string, last_name?: string, username?: string, phone?: string, avatar_path?: string }
     */
    public function getFullTelegramUserInfo(string $userId): array
    {
        try {
            $result = $this->run(function () use ($userId) {
                $api = $this->getApi();
                $api->start();
                $info = $api->getFullInfo((int) $userId);
                $user = $info['User'] ?? null;
                if (! $user || ! \is_array($user)) {
                    return [];
                }
                $full = $info['full'] ?? $info['Full'] ?? [];
                $firstName = $user['first_name'] ?? '';
                $lastName = $user['last_name'] ?? '';
                $username = $user['username'] ?? null;
                $phone = $user['phone'] ?? $full['phone'] ?? null;

                $avatarPath = null;
                $profilePhoto = $full['profile_photo'] ?? null;
                if ($profilePhoto && isset($profilePhoto['sizes']) && \is_array($profilePhoto['sizes']) && ! empty($profilePhoto['sizes'])) {
                    $largest = $profilePhoto['sizes'][\count($profilePhoto['sizes']) - 1] ?? $profilePhoto['sizes'][0];
                    if (($largest['_'] ?? '') === 'photoSize' || ($largest['_'] ?? '') === 'photoCachedSize') {
                        try {
                            $dir = storage_path('app/public/telegram-avatars');
                            if (! is_dir($dir)) {
                                mkdir($dir, 0755, true);
                            }
                            $filePath = $dir.'/user_'.$userId.'_'.time().'.jpg';
                            $api->downloadToFile($profilePhoto, $filePath);
                            $avatarPath = 'telegram-avatars/'.basename($filePath);
                        } catch (\Throwable $e) {
                            Log::debug('MadelineProto profile photo download failed: '.$e->getMessage());
                        }
                    }
                }

                return [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'phone' => $phone,
                    'avatar_path' => $avatarPath,
                ];
            });

            return \is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            Log::warning('MadelineProto getFullTelegramUserInfo failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Extract message ID from API result (array or Message object).
     */
    protected function extractMessageId(mixed $result): ?int
    {
        if (\is_array($result) && isset($result['id'])) {
            return (int) $result['id'];
        }
        if (\is_object($result) && isset($result->id)) {
            return (int) $result->id;
        }

        return null;
    }

    /**
     * Resolve media path/URL to MadelineProto file object for sendPhoto.
     */
    protected function resolveMediaFile(?string $pathOrUrl)
    {
        if (! $pathOrUrl || trim($pathOrUrl) === '') {
            return null;
        }
        $pathOrUrl = trim($pathOrUrl);
        if (str_starts_with(strtolower($pathOrUrl), 'http://') || str_starts_with(strtolower($pathOrUrl), 'https://')) {
            return new \danog\MadelineProto\RemoteUrl($pathOrUrl);
        }
        $localPath = $pathOrUrl;
        if (! file_exists($pathOrUrl) && ! str_starts_with($pathOrUrl, '/') && ! preg_match('#^[A-Za-z]:[\\\\/]#', $pathOrUrl)) {
            $localPath = storage_path('app/public/'.ltrim($pathOrUrl, '/'));
        }
        if (file_exists($localPath)) {
            return new \danog\MadelineProto\LocalFile($localPath);
        }
        if (file_exists($pathOrUrl)) {
            return new \danog\MadelineProto\LocalFile($pathOrUrl);
        }

        return null;
    }

    protected function extractUserId($fromId): ?string
    {
        if ($fromId === null) {
            return null;
        }
        // MadelineProto can return from_id as plain integer (user_id)
        if (is_numeric($fromId)) {
            return (string) (int) $fromId;
        }
        if (! is_array($fromId)) {
            return null;
        }
        $t = $fromId['_'] ?? '';
        if ($t === 'peerUser') {
            return (string) ($fromId['user_id'] ?? null);
        }
        if ($t === 'peerChannel') {
            return null;
        }
        if (isset($fromId['user_id'])) {
            return (string) $fromId['user_id'];
        }

        return null;
    }

    /**
     * Send private message to user. Accepts user ID (numeric) or username (@handle or handle).
     *
     * @param  string  $peer  Telegram user ID (e.g. "5166408066") or username ("@ronakpanahi" or "ronakpanahi")
     * @param  string  $text  Message text (or caption when image is present)
     * @param  string|null  $imagePath  Full path to image file, or URL for RemoteUrl
     * @return array { success: bool, message_id?: int, error?: string, resolved_chat_id?: string }
     */
    public function sendPrivateMessage(string $peer, string $text, ?string $imagePath = null): array
    {
        $peer = trim($peer);
        $isNumeric = ctype_digit($peer) || (is_numeric($peer) && (string) (int) $peer === $peer);
        $apiPeer = $isNumeric ? (int) $peer : ($peer[0] === '@' ? $peer : '@'.$peer);

        Log::info('MadelineProtoService::sendPrivateMessage', ['peer' => $apiPeer, 'has_image' => ! empty($imagePath)]);
        try {
            $out = $this->run(function () use ($apiPeer, $text, $imagePath) {
                $api = $this->getApi();
                $api->start();
                $file = $this->resolveMediaFile($imagePath);
                $result = null;
                if ($file) {
                    try {
                        $result = $api->sendPhoto(peer: $apiPeer, file: $file, caption: $text);
                    } catch (\Throwable $e) {
                        if (str_contains($e->getMessage(), 'Return value') || str_contains($e->getMessage(), 'as array') || str_contains($e->getMessage(), 'PrivateMessage')) {
                            Log::warning('MadelineProto sendPhoto fallback to text: '.$e->getMessage());
                            $result = $api->messages->sendMessage(peer: $apiPeer, message: $text);
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    $result = $api->messages->sendMessage(peer: $apiPeer, message: $text);
                }
                $msgId = $this->extractMessageId($result);
                $resolvedId = (string) $api->getId($apiPeer);

                return ['message_id' => $msgId, 'resolved_chat_id' => $resolvedId];
            });

            $this->connection?->update(['last_used_at' => now()]);

            return [
                'success' => true,
                'message_id' => $out['message_id'] ?? null,
                'resolved_chat_id' => $out['resolved_chat_id'] ?? $peer,
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $detail = $msg !== '' ? $msg : self::exceptionSummary($e);
            // MadelineProto sometimes returns PrivateMessage object; internal code may throw "as array"
            // while the message was actually sent. Treat as success so UI shows correct sent count.
            if (str_contains($msg, 'PrivateMessage') && str_contains($msg, 'as array')) {
                Log::info('MadelineProto sendMessage: message sent but lib threw as-array (treating as success)');
                $this->connection?->update(['last_used_at' => now()]);
                $resolvedId = $peer;
                try {
                    $resolvedId = $this->run(function () use ($apiPeer) {
                        $api = $this->getApi();
                        $api->start();

                        return (string) $api->getId($apiPeer);
                    });
                } catch (\Throwable $ex) {
                    Log::debug('MadelineProto: resolve peer after as-array send failed', ['error' => $ex->getMessage()]);
                }

                return [
                    'success' => true,
                    'message_id' => null,
                    'resolved_chat_id' => $resolvedId,
                ];
            }
            Log::warning('MadelineProto sendMessage failed', [
                'detail' => $detail,
                'class' => get_class($e),
            ]);

            return ['success' => false, 'error' => $detail];
        }
    }

    /**
     * Send message to a group (as group post).
     *
     * @param  string  $groupId  Group/Channel ID (e.g. -1001234567890)
     * @param  string  $text  Message text (or caption when image is present)
     * @param  string|null  $imagePath  Full path to image file
     * @return array { success: bool, message_id?: int, error?: string }
     */
    public function sendGroupMessage(string $groupId, string $text, ?string $imagePath = null): array
    {
        try {
            $messageId = $this->run(function () use ($groupId, $text, $imagePath) {
                $api = $this->getApi();
                $api->start();
                if ($imagePath && file_exists($imagePath)) {
                    try {
                        $file = new \danog\MadelineProto\LocalFile($imagePath);
                        $result = $api->sendPhoto(peer: $groupId, file: $file, caption: $text);

                        return $this->extractMessageId($result);
                    } catch (\Throwable $e) {
                        if (str_contains($e->getMessage(), 'Return value') || str_contains($e->getMessage(), 'sendMedia') || str_contains($e->getMessage(), 'as array')) {
                            Log::warning('MadelineProto sendPhoto fallback to text: '.$e->getMessage());
                            $result = $api->messages->sendMessage(peer: $groupId, message: $text);

                            return $this->extractMessageId($result);
                        }
                        throw $e;
                    }
                }
                $result = $api->messages->sendMessage(peer: $groupId, message: $text);

                return $this->extractMessageId($result);
            });
            $this->connection?->update(['last_used_at' => now()]);

            return ['success' => true, 'message_id' => $messageId];
        } catch (\Throwable $e) {
            Log::warning('MadelineProto sendGroupMessage failed: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Find connection by ID, or find/create a pending one for login flows.
     */
    protected function findOrCreateConnection(?int $connId): TelegramUserConnection
    {
        if ($connId) {
            $conn = TelegramUserConnection::find($connId);
            if ($conn) {
                if ($conn->status === 'expired') {
                    $conn->update(['status' => 'pending']);
                }
                return $conn;
            }
        }
        $conn = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
            ->orderByDesc('updated_at')
            ->first();

        return $conn ?? TelegramUserConnection::create([
            'status' => 'pending',
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);
    }

    /**
     * Mark connection as connected after successful login.
     */
    protected function markConnected(TelegramUserConnection $conn, $api): void
    {
        $self = $api->getSelf();
        $conn->update([
            'status' => 'connected',
            'auth_flow' => null,
            'phone' => $self['phone'] ?? null,
            'telegram_username' => $self['username'] ?? null,
        ]);
    }

    /**
     * Initiate QR login or poll for scan result.
     * Simple flow: find conn by ID → open same session → check auth / generate QR.
     */
    public function getQrCode(bool $wait = false, ?int $connId = null): array
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            return ['error' => 'MadelineProto is not installed.', 'logged_in' => false];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');
        if (! $apiId || ! $apiHash) {
            return ['error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env', 'logged_in' => false];
        }

        try {
            $conn = $this->findOrCreateConnection($connId);
            $sessionPath = $conn->getSessionPath();
            $this->connection = $conn;

            if (! is_dir($sessionPath)) {
                mkdir($sessionPath, 0755, true);
            }

            Log::info('MadelineProto getQrCode', ['conn_id' => $conn->id, 'wait' => $wait, 'session' => $sessionPath]);

            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash, $wait) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = self::withForcedFullMadelineInstance(fn () => new \danog\MadelineProto\API($sessionPath, $settings));

                if ($api->getAuthorization() === \danog\MadelineProto\API::LOGGED_IN) {
                    $this->markConnected($conn, $api);
                    return ['logged_in' => true, 'conn_id' => $conn->id];
                }

                $qr = $api->qrLogin();
                if ($qr && $wait) {
                    try {
                        $qr = $qr->waitForLoginOrQrCodeExpiration(
                            \danog\MadelineProto\Tools::getTimeoutCancellation(10.0)
                        );
                    } catch (\Amp\CancelledException) {
                        $qr = $api->qrLogin();
                    }
                }

                $auth = $api->getAuthorization();
                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $this->markConnected($conn, $api);
                    return ['logged_in' => true, 'conn_id' => $conn->id];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    return ['logged_in' => false, 'needs_2fa' => true, 'conn_id' => $conn->id];
                }
                if (! $qr) {
                    return ['logged_in' => false, 'conn_id' => $conn->id];
                }

                return [
                    'logged_in' => false,
                    'qr_svg' => $qr->getQRSvg(400, 2),
                    'conn_id' => $conn->id,
                ];
            });

            return is_array($result) ? $result : ['logged_in' => false, 'error' => 'Unknown response'];
        } catch (\Throwable $e) {
            Log::error('Telegram getQrCode error: '.$e->getMessage());
            return ['error' => $e->getMessage(), 'logged_in' => false];
        }
    }

    /**
     * Read current auth status from session (without generating QR).
     */
    public function getConnectionStatus(?int $connId = null): array
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            return ['logged_in' => false, 'error' => 'MadelineProto is not installed.'];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');
        if (! $apiId || ! $apiHash) {
            return ['logged_in' => false, 'error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env'];
        }

        try {
            $conn = $connId ? TelegramUserConnection::find($connId) : null;
            if (! $conn) {
                $conn = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
                    ->orderByDesc('updated_at')->first();
            }
            if (! $conn) {
                return ['logged_in' => false];
            }

            $sessionPath = $conn->getSessionPath();
            $this->connection = $conn;

            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = self::withForcedFullMadelineInstance(fn () => new \danog\MadelineProto\API($sessionPath, $settings));
                $auth = $api->getAuthorization();

                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $this->markConnected($conn, $api);

                    return ['logged_in' => true, 'conn_id' => $conn->id];
                }

                return [
                    'logged_in' => false,
                    'conn_id' => $conn->id,
                    'needs_2fa' => $auth === \danog\MadelineProto\API::WAITING_PASSWORD,
                    'waiting_code' => $auth === \danog\MadelineProto\API::WAITING_CODE,
                ];
            });

            return is_array($result) ? $result : ['logged_in' => false];
        } catch (\Throwable $e) {
            Log::error('Telegram getConnectionStatus error: '.$e->getMessage());
            return ['logged_in' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Start phone login — send OTP to the given phone number.
     */
    public function startPhoneLogin(string $phone, ?int $connId = null): array
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            return ['success' => false, 'logged_in' => false, 'error' => 'MadelineProto is not installed.'];
        }
        $phone = trim($phone);
        if ($phone === '') {
            return ['success' => false, 'logged_in' => false, 'error' => 'Phone number is required.'];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');
        if (! $apiId || ! $apiHash) {
            return ['success' => false, 'logged_in' => false, 'error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env'];
        }

        try {
            $conn = $this->findOrCreateConnection($connId);
            $sessionPath = $conn->getSessionPath();
            $this->connection = $conn;
            if (! is_dir($sessionPath)) {
                mkdir($sessionPath, 0755, true);
            }

            Log::info('MadelineProto startPhoneLogin', ['conn_id' => $conn->id, 'session' => $sessionPath]);

            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash, $phone) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = self::withForcedFullMadelineInstance(fn () => new \danog\MadelineProto\API($sessionPath, $settings));
                $api->phoneLogin($phone);
                $auth = $api->getAuthorization();

                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $this->markConnected($conn, $api);
                    return ['success' => true, 'logged_in' => true, 'conn_id' => $conn->id];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_CODE) {
                    return ['success' => true, 'logged_in' => false, 'waiting_code' => true, 'conn_id' => $conn->id];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    return ['success' => true, 'logged_in' => false, 'needs_2fa' => true, 'conn_id' => $conn->id];
                }

                return ['success' => false, 'logged_in' => false, 'conn_id' => $conn->id, 'error' => 'Unexpected auth state.'];
            });

            return is_array($result) ? $result : ['success' => false, 'logged_in' => false, 'error' => 'Unexpected response.'];
        } catch (\Throwable $e) {
            Log::error('Telegram startPhoneLogin error: '.$e->getMessage());
            return ['success' => false, 'logged_in' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Complete phone login with OTP code.
     */
    public function completePhoneLogin(string $code, ?int $connId = null): array
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            return ['success' => false, 'logged_in' => false, 'error' => 'MadelineProto is not installed.'];
        }
        $code = preg_replace('/\s+/', '', trim($code));
        if ($code === '') {
            return ['success' => false, 'logged_in' => false, 'error' => 'OTP code is required.'];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');
        if (! $apiId || ! $apiHash) {
            return ['success' => false, 'logged_in' => false, 'error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env'];
        }

        try {
            $conn = $connId ? TelegramUserConnection::find($connId) : null;
            if (! $conn) {
                $conn = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
                    ->orderByDesc('updated_at')->first();
            }
            if (! $conn) {
                return ['success' => false, 'logged_in' => false, 'error' => 'No pending Telegram session found. Start phone login first.'];
            }

            $sessionPath = $conn->getSessionPath();
            $this->connection = $conn;

            Log::info('Telegram completePhoneLogin', ['conn_id' => $conn->id]);

            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash, $code) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = self::withForcedFullMadelineInstance(fn () => new \danog\MadelineProto\API($sessionPath, $settings));
                $api->completePhoneLogin($code);
                $auth = $api->getAuthorization();

                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $this->markConnected($conn, $api);
                    return ['success' => true, 'logged_in' => true, 'conn_id' => $conn->id];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    return ['success' => true, 'logged_in' => false, 'needs_2fa' => true, 'conn_id' => $conn->id];
                }

                return ['success' => false, 'logged_in' => false, 'conn_id' => $conn->id, 'error' => 'Telegram is not fully authenticated yet.'];
            });

            return is_array($result) ? $result : ['success' => false, 'logged_in' => false, 'error' => 'Unexpected OTP response.'];
        } catch (\Throwable $e) {
            Log::error('Telegram completePhoneLogin error: '.$e->getMessage());
            return ['success' => false, 'logged_in' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Complete 2FA login (cloud password) after QR or phone login.
     */
    public function complete2faLogin(string $password, ?int $connId = null): array
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            return ['success' => false, 'error' => 'MadelineProto is not installed.'];
        }
        $password = trim($password);
        if ($password === '') {
            return ['success' => false, 'error' => 'Password is required.'];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');
        if (! $apiId || ! $apiHash) {
            return ['success' => false, 'error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env'];
        }

        try {
            $conn = $connId ? TelegramUserConnection::find($connId) : null;
            if (! $conn) {
                $conn = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
                    ->orderByDesc('updated_at')->first();
            }
            if (! $conn) {
                return ['success' => false, 'error' => 'No pending Telegram session found.'];
            }

            $sessionPath = $conn->getSessionPath();
            $this->connection = $conn;

            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash, $password) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = self::withForcedFullMadelineInstance(fn () => new \danog\MadelineProto\API($sessionPath, $settings));
                if ($api->getAuthorization() === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    $api->complete2faLogin($password);
                }
                $auth = $api->getAuthorization();
                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $this->markConnected($conn, $api);
                    return ['success' => true, 'logged_in' => true];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    return ['success' => false, 'logged_in' => false, 'needs_2fa' => true, 'error' => 'Invalid 2FA password.'];
                }
                return ['success' => false, 'logged_in' => false, 'error' => 'Telegram is not fully authenticated yet.'];
            });

            return is_array($result) ? $result : ['success' => false, 'logged_in' => false, 'error' => 'Unexpected 2FA response.'];
        } catch (\Throwable $e) {
            Log::error('Telegram complete2faLogin error: '.$e->getMessage());
            return ['success' => false, 'logged_in' => false, 'error' => $e->getMessage()];
        }
    }
}
