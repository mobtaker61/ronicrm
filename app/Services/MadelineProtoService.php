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
        $this->connection = $connection ?? TelegramUserConnection::getActive();
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
        $apiId = (int) $this->connection->getApiId();
        $apiHash = $this->connection->getApiHash();
        if (! $apiId || ! $apiHash) {
            $apiId = (int) config('services.telegram.api_id');
            $apiHash = config('services.telegram.api_hash');
        }
        $settings = $this->makeMadelineSettings($apiId, $apiHash);
        $this->api = new \danog\MadelineProto\API($sessionPath, $settings);

        return $this->api;
    }

    /** Max seconds for any MadelineProto operation (prevents hanging requests). */
    protected int $runTimeout = 180;

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
        $connId = $this->connection?->id ?? 0;
        $lockKey = 'madeline_session_'.$connId;
        $lock = Cache::lock($lockKey, 600);
        if (! $lock->block(120)) {
            throw new \RuntimeException('Could not acquire Telegram session lock (another operation in progress). Try again later.');
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
            $msg = $error->getMessage() ?: '(no message)';
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

    /**
     * Create MadelineProto Settings with API credentials and FILE_LOGGER.
     * Using FILE_LOGGER avoids "fwrite(): supplied resource is not a valid stream resource"
     * when stdout is invalid (e.g. queue workers on shared hosting).
     */
    /**
     * Check if any pending session file is already logged in (user scanned in different tab/request).
     * Updates the conn and returns logged_in response if found.
     */
    protected function checkAnySessionLoggedIn(int $apiId, string $apiHash): ?array
    {
        $pendings = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
            ->whereNotNull('session_path')
            ->orderByDesc('updated_at')
            ->get();
        foreach ($pendings as $c) {
            $path = storage_path('app/'.$c->session_path);
            if (! is_dir($path) && ! file_exists($path)) {
                continue;
            }
            try {
                $this->connection = $c;
                $result = $this->run(function () use ($c, $path, $apiId, $apiHash) {
                    $settings = $this->makeMadelineSettings($apiId, $apiHash);
                    $api = new \danog\MadelineProto\API($path, $settings);
                    if ($api->getAuthorization() === \danog\MadelineProto\API::LOGGED_IN) {
                        $self = $api->getSelf();
                        $c->update([
                            'status' => 'connected',
                            'phone' => $self['phone'] ?? null,
                            'telegram_username' => $self['username'] ?? null,
                        ]);

                        return true;
                    }

                    return false;
                });
                if ($result) {
                    $userId = \Illuminate\Support\Facades\Auth::id() ?? 0;
                    Cache::forget(self::CACHE_KEY_QR_CONN.'_'.$userId);

                    return ['logged_in' => true];
                }
            } catch (\Throwable $e) {
                Log::debug('MadelineProto checkSessionLoggedIn: '.$e->getMessage());
            }
        }

        return null;
    }

    protected function makeMadelineSettings(int $apiId, string $apiHash): \danog\MadelineProto\Settings
    {
        $settings = new \danog\MadelineProto\Settings;
        $settings->getAppInfo()->setApiId($apiId)->setApiHash($apiHash);
        $settings->getLogger()
            ->setType(\danog\MadelineProto\Logger::LOGGER_FILE)
            ->setExtra(storage_path('logs/madelineproto.log'));

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
        return $this->run(function () {
            $api = $this->getApi();
            $api->start();
            $dialogs = $api->getFullDialogs();
            $result = [];
            foreach ($dialogs as $dialog) {
                $peer = $dialog['peer'] ?? null;
                if (! $peer) {
                    continue;
                }
                $chat = $dialog['peer'] ?? $dialog;
                $id = $api->getId($peer);
                $title = $this->extractDialogTitle($dialog, $api);
                $type = $this->getPeerType($peer, $api);
                $result[] = [
                    'id' => (string) $id,
                    'title' => $title,
                    'type' => $type,
                ];
            }

            return $result;
        });
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
        return $this->run(function () use ($userId, $limit, $minId) {
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
            // MadelineProto sometimes returns PrivateMessage object; internal code may throw "as array"
            // while the message was actually sent. Treat as success so UI shows correct sent count.
            if (str_contains($msg, 'PrivateMessage') && str_contains($msg, 'as array')) {
                Log::info('MadelineProto sendMessage: message sent but lib threw as-array (treating as success)');
                $this->connection?->update(['last_used_at' => now()]);

                return ['success' => true, 'message_id' => null];
            }
            Log::warning('MadelineProto sendMessage failed: '.$msg);

            return ['success' => false, 'error' => $msg];
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
     * Get existing pending/connected or create one.
     * NEVER expire during QR flow - prevents session mismatch (user scans session_84, we create session_85).
     * Only create when we have zero pending/connected. Reuse existing to ensure same session for QR + poll.
     */
    protected function getOrCreatePendingConnection(): TelegramUserConnection
    {
        $conn = TelegramUserConnection::whereIn('status', ['pending', 'connected'])
            ->orderByDesc('updated_at')
            ->first();

        return $conn ?? TelegramUserConnection::create(['status' => 'pending']);
    }

    protected function deleteSessionFolder(TelegramUserConnection $conn): void
    {
        if (! $conn->session_path) {
            return;
        }
        $path = storage_path('app/'.$conn->session_path);
        try {
            if (is_dir($path)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        } catch (\Throwable $e) {
            Log::warning('MadelineProto could not delete session folder: '.$e->getMessage());
        }
    }

    /** Cache key for "current QR connection" - ensures all requests use same session during QR flow */
    public const CACHE_KEY_QR_CONN = 'telegram_qr_conn';

    /**
     * Initiate QR login. Returns SVG of QR code or null if already logged in.
     *
     * @param  bool  $wait  If true, wait up to 5s for user to scan (for polling).
     * @param  int|null  $connId  If set, use this specific connection (ensures poll uses same session as QR).
     * @return array { qr_svg?: string, conn_id?: int, logged_in: bool, needs_2fa?: bool, error?: string }
     */
    public function getQrCode(bool $wait = false, ?int $connId = null): array
    {
        if (! class_exists(\danog\MadelineProto\API::class)) {
            return ['error' => 'MadelineProto is not installed. Run: composer require danog/madelineproto'];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = config('services.telegram.api_hash');
        if (! $apiId || ! $apiHash) {
            return ['error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env'];
        }

        try {
            $userId = \Illuminate\Support\Facades\Auth::id() ?? 0;
            $cacheKey = self::CACHE_KEY_QR_CONN.'_'.$userId;

            $conn = null;
            $cachedConnId = Cache::get($cacheKey);
            $connIdToUse = $connId ?: $cachedConnId;

            if ($connIdToUse) {
                $conn = TelegramUserConnection::find($connIdToUse);
                if (! $conn || ! in_array($conn->status, ['pending', 'connected'], true)) {
                    $conn = null;
                    Cache::forget($cacheKey);
                }
            }

            if (! $conn) {
                if ($wait) {
                    return ['error' => 'QR session expired. Please click "Connect via QR Code" again.', 'logged_in' => false];
                }
                $conn = $this->connection ?? TelegramUserConnection::getActive()
                    ?? $this->getOrCreatePendingConnection();
                Cache::put($cacheKey, $conn->id, now()->addMinutes(15));
            }

            $alreadyLoggedIn = $this->checkAnySessionLoggedIn($apiId, $apiHash);
            if ($alreadyLoggedIn) {
                return $alreadyLoggedIn;
            }

            $sessionPath = $conn->getSessionPath();
            Log::info('MadelineProto getQrCode: starting', ['session_path' => $sessionPath, 'conn_id' => $conn->id, 'wait' => $wait]);

            $this->connection = $conn;

            // On Windows, Amp\File's ParallelFilesystemDriver often fails on createDirectory.
            // Pre-create the session directory with native PHP so MadelineProto skips it.
            if (! is_dir($sessionPath)) {
                mkdir($sessionPath, 0755, true);
            }

            // Run inside EventLoop - MadelineProto requires it for async I/O
            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash, $wait) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = new \danog\MadelineProto\API($sessionPath, $settings);
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
                Log::info('MadelineProto getQrCode: qrLogin done', [
                    'qr_is_null' => $qr === null,
                    'qr_class' => $qr ? get_class($qr) : null,
                    'auth' => $api->getAuthorization(),
                ]);
                $auth = $api->getAuthorization();
                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $self = $api->getSelf();
                    $conn->update([
                        'status' => 'connected',
                        'phone' => $self['phone'] ?? null,
                        'telegram_username' => $self['username'] ?? null,
                    ]);
                    $userId = \Illuminate\Support\Facades\Auth::id() ?? 0;
                    Cache::forget(self::CACHE_KEY_QR_CONN.'_'.$userId);

                    return ['logged_in' => true];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    return [
                        'logged_in' => false,
                        'needs_2fa' => true,
                        'conn_id' => $conn->id,
                    ];
                }
                if (! $qr) {
                    return ['logged_in' => false];
                }

                // Persist session so next poll returns same QR instead of creating a new one
                // serialize() is on APIWrapper, not API - access via reflection
                $ref = new \ReflectionClass($api);
                $wrapperProp = $ref->getProperty('wrapper');
                $wrapperProp->setAccessible(true);
                $wrapper = $wrapperProp->getValue($api);
                if (method_exists($wrapper, 'serialize')) {
                    $wrapper->serialize();
                }

                return [
                    'logged_in' => false,
                    'qr_svg' => $qr->getQRSvg(400, 2),
                    'conn_id' => $conn->id,
                ];
            });

            Log::info('MadelineProto getQrCode: after run', [
                'result_type' => gettype($result),
                'result' => is_array($result) ? array_keys($result) : $result,
            ]);

            return is_array($result) ? $result : ['logged_in' => false, 'error' => 'Unknown response (type: '.gettype($result).')'];
        } catch (\Throwable $e) {
            Log::error('Telegram getQrCode error: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'logged_in' => false];
        }
    }

    /**
     * Complete Telegram 2FA login (cloud password) after QR scan.
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
            $userId = \Illuminate\Support\Facades\Auth::id() ?? 0;
            $cacheKey = self::CACHE_KEY_QR_CONN.'_'.$userId;
            $connIdToUse = $connId ?: (Cache::get($cacheKey) ?: null);
            $conn = $connIdToUse
                ? TelegramUserConnection::find($connIdToUse)
                : (TelegramUserConnection::whereIn('status', ['pending', 'connected'])->orderByDesc('updated_at')->first());

            if (! $conn) {
                return ['success' => false, 'error' => 'No pending Telegram session found. Please re-open QR login.'];
            }

            $sessionPath = $conn->getSessionPath();
            $this->connection = $conn;

            $result = $this->run(function () use ($conn, $sessionPath, $apiId, $apiHash, $password) {
                $settings = $this->makeMadelineSettings($apiId, $apiHash);
                $api = new \danog\MadelineProto\API($sessionPath, $settings);
                $auth = $api->getAuthorization();
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    $api->complete2faLogin($password);
                }

                $auth = $api->getAuthorization();
                if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                    $self = $api->getSelf();
                    $conn->update([
                        'status' => 'connected',
                        'phone' => $self['phone'] ?? null,
                        'telegram_username' => $self['username'] ?? null,
                    ]);

                    return ['success' => true, 'logged_in' => true];
                }
                if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                    return ['success' => false, 'logged_in' => false, 'needs_2fa' => true, 'error' => 'Invalid 2FA password.'];
                }

                return ['success' => false, 'logged_in' => false, 'error' => 'Telegram is not fully authenticated yet.'];
            });

            if (($result['logged_in'] ?? false) === true) {
                Cache::forget($cacheKey);
            }

            return is_array($result) ? $result : ['success' => false, 'logged_in' => false, 'error' => 'Unexpected 2FA response.'];
        } catch (\Throwable $e) {
            Log::error('Telegram complete2faLogin error: '.$e->getMessage());

            return ['success' => false, 'logged_in' => false, 'error' => $e->getMessage()];
        }
    }
}
