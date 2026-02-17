<?php

namespace App\Services;

use App\Models\TelegramUserConnection;
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
        if (!$this->connection || !$this->connection->isConnected()) {
            throw new \RuntimeException('No active Telegram user connection.');
        }
        if (!class_exists(\danog\MadelineProto\API::class)) {
            throw new \RuntimeException('MadelineProto is not installed. Run: composer require danog/madelineproto');
        }
        $sessionPath = $this->connection->getSessionPath();
        $apiId = (int) $this->connection->getApiId();
        $apiHash = $this->connection->getApiHash();
        if (!$apiId || !$apiHash) {
            $apiId = (int) config('services.telegram.api_id');
            $apiHash = config('services.telegram.api_hash');
        }
        $settings = $this->makeMadelineSettings($apiId, $apiHash);
        $this->api = new \danog\MadelineProto\API($sessionPath, $settings);
        return $this->api;
    }

    /** Max seconds for any MadelineProto operation (prevents hanging requests). */
    protected int $runTimeout = 90;

    /**
     * Run async closure and return result (blocking).
     * Uses explicit EventLoop::run() so the event loop processes async I/O correctly in web context.
     * Includes a safety timeout to prevent indefinite blocking.
     */
    protected function run(callable $callback)
    {
        if (!class_exists(\danog\MadelineProto\API::class)) {
            throw new \RuntimeException('MadelineProto is not installed. Run: composer require danog/madelineproto');
        }
        $result = null;
        $error = null;
        $timeoutId = \Revolt\EventLoop::delay($this->runTimeout, function () {
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
        if ($error !== null) {
            Log::error('MadelineProto run() error', [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString(),
            ]);
            throw $error;
        }
        return $result;
    }

    /**
     * Create MadelineProto Settings with API credentials and FILE_LOGGER.
     * Using FILE_LOGGER avoids "fwrite(): supplied resource is not a valid stream resource"
     * when stdout is invalid (e.g. queue workers on shared hosting).
     */
    protected function makeMadelineSettings(int $apiId, string $apiHash): \danog\MadelineProto\Settings
    {
        $settings = new \danog\MadelineProto\Settings();
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
                if (!$peer) continue;
                $chat = $dialog['peer'] ?? $dialog;
                $id = $api->getId($peer);
                $title = $this->extractDialogTitle($dialog, $api);
                $type = $this->getPeerType($peer);
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
        if (!$peer) return 'Unknown';
        if (isset($peer['title'])) return $peer['title'];
        if (isset($peer['first_name'])) {
            return trim(($peer['first_name'] ?? '') . ' ' . ($peer['last_name'] ?? ''));
        }
        if (isset($dialog['name'])) return $dialog['name'];
        try {
            $entity = $api->getInfo($peer);
            return $entity['User']['first_name'] ?? $entity['Chat']['title'] ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    protected function getPeerType($peer): string
    {
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
     * @param string $peerId Chat/group ID (e.g. -1001234567890)
     * @param int $limit Number of messages to fetch
     * @return array{valid: array, all: array} valid=for authors, all=every message for preview
     */
    public function getGroupMessages(string $peerId, int $limit = 50): array
    {
        return $this->run(function () use ($peerId, $limit) {
            $api = $this->getApi();
            $api->start();
            $fetchPeerId = $peerId;
            $info = $api->getInfo($peerId);
            $isChannel = ($info['Chat']['broadcast'] ?? false) && !($info['Chat']['megagroup'] ?? false);
            if ($isChannel) {
                $full = $api->getFullInfo($peerId);
                $linkedChatId = $full['linked_chat_id'] ?? $full['full_chat']['linked_chat_id'] ?? null;
                if ($linkedChatId) {
                    $fetchPeerId = '-100' . $linkedChatId;
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
                if (!$fromId && !empty($msg['fwd_from']['from_id'])) {
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
        if ($msgId === null) return null;
        return "https://t.me/c/{$channelId}/{$msgId}";
    }

    protected function extractUserId($fromId): ?string
    {
        if ($fromId === null) return null;
        // MadelineProto can return from_id as plain integer (user_id)
        if (is_numeric($fromId)) {
            return (string) (int) $fromId;
        }
        if (!is_array($fromId)) return null;
        $t = $fromId['_'] ?? '';
        if ($t === 'peerUser') {
            return (string) ($fromId['user_id'] ?? null);
        }
        if ($t === 'peerChannel') return null;
        if (isset($fromId['user_id'])) return (string) $fromId['user_id'];
        return null;
    }

    /**
     * Send private message to user.
     *
     * @param string $userId Telegram user ID (for PM, chat_id = user_id)
     * @param string $text Message text
     * @return array { success: bool, message_id?: int, error?: string }
     */
    public function sendPrivateMessage(string $userId, string $text): array
    {
        Log::info('MadelineProtoService::sendPrivateMessage', ['user_id' => $userId]);
        try {
            $messageId = $this->run(function () use ($userId, $text) {
                $api = $this->getApi();
                $api->start();
                $result = $api->messages->sendMessage(peer: (int) $userId, message: $text);
                return $result['id'] ?? null;
            });
            $this->connection?->update(['last_used_at' => now()]);
            return ['success' => true, 'message_id' => $messageId];
        } catch (\Throwable $e) {
            Log::warning('MadelineProto sendMessage failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Initiate QR login. Returns SVG of QR code or null if already logged in.
     *
     * @param bool $wait If true, wait up to 5s for user to scan (for polling).
     * @return array { qr_svg?: string, logged_in: bool, needs_2fa?: bool, error?: string }
     */
    public function getQrCode(bool $wait = false): array
    {
        if (!class_exists(\danog\MadelineProto\API::class)) {
            return ['error' => 'MadelineProto is not installed. Run: composer require danog/madelineproto'];
        }
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = config('services.telegram.api_hash');
        if (!$apiId || !$apiHash) {
            return ['error' => 'TELEGRAM_API_ID and TELEGRAM_API_HASH must be set in .env'];
        }
        try {
            $conn = $this->connection ?? TelegramUserConnection::getActive()
                ?? TelegramUserConnection::whereIn('status', ['pending', 'connected'])->orderByDesc('updated_at')->first()
                ?? TelegramUserConnection::create(['status' => 'pending']);
            $sessionPath = $conn->getSessionPath();
            Log::info('MadelineProto getQrCode: starting', ['session_path' => $sessionPath]);

            // On Windows, Amp\File's ParallelFilesystemDriver often fails on createDirectory.
            // Pre-create the session directory with native PHP so MadelineProto skips it.
            if (!is_dir($sessionPath)) {
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
                            \danog\MadelineProto\Tools::getTimeoutCancellation(5.0)
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
                if (!$qr) {
                    $auth = $api->getAuthorization();
                    if ($auth === \danog\MadelineProto\API::LOGGED_IN) {
                        $conn->update(['status' => 'connected']);
                        return ['logged_in' => true];
                    }
                    if ($auth === \danog\MadelineProto\API::WAITING_PASSWORD) {
                        return ['logged_in' => false, 'needs_2fa' => true];
                    }
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
                ];
            });

            Log::info('MadelineProto getQrCode: after run', [
                'result_type' => gettype($result),
                'result' => is_array($result) ? array_keys($result) : $result,
            ]);
            return is_array($result) ? $result : ['logged_in' => false, 'error' => 'Unknown response (type: ' . gettype($result) . ')'];
        } catch (\Throwable $e) {
            Log::error('Telegram getQrCode error: ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'logged_in' => false];
        }
    }
}
