<?php

namespace App\Jobs;

use App\Models\InstagramMessage;
use App\Models\TelegramMessage;
use App\Models\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MarkInboxConversationReadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public string $channel,
        public string $contactKey,
        public array $specificIds = []
    ) {}

    public function handle(): void
    {
        try {
            if ($this->channel === 'telegram') {
                $q = TelegramMessage::forChat($this->contactKey)
                    ->where('direction', 'incoming')
                    ->whereNull('read_at');
                if ($this->specificIds !== []) {
                    $q->whereIn('id', $this->specificIds);
                }
                $updated = $q->update(['read_at' => now(), 'status' => 'read']);
                Log::info('MarkInboxConversationReadJob: telegram marked read', [
                    'contact' => $this->contactKey,
                    'updated' => $updated,
                    'ids_count' => count($this->specificIds),
                ]);

                return;
            }

            if ($this->channel === 'instagram') {
                $updated = InstagramMessage::forIgUser($this->contactKey)
                    ->whereNull('read_at')
                    ->update(['read_at' => now(), 'status' => 'read']);
                Log::info('MarkInboxConversationReadJob: instagram marked read', [
                    'contact' => $this->contactKey,
                    'updated' => $updated,
                ]);

                return;
            }

            $updated = WhatsAppMessage::where('from_phone', $this->contactKey)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'status' => 'read']);
            Log::info('MarkInboxConversationReadJob: whatsapp marked read', [
                'contact' => $this->contactKey,
                'updated' => $updated,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), '1205') && $this->attempts() < $this->tries) {
                $this->release(2);
                return;
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::warning('MarkInboxConversationReadJob failed', [
                'channel' => $this->channel,
                'contact' => $this->contactKey,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $e;
        }
    }
}

