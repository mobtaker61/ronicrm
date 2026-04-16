<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:fetch-incoming', function () {
    $this->info('Running TelegramFetchIncomingJob...');
    dispatch_sync(new \App\Jobs\TelegramFetchIncomingJob);
    $this->info('Done.');
})->purpose('Fetch incoming Telegram DMs and sync to inbox (run manually)');

Artisan::command('telegram:sync-contacts', function () {
    $conn = \App\Models\TelegramUserConnection::getActive();
    if (!$conn || !$conn->isConnected()) {
        $this->error('No active Telegram connection. Connect in Settings → Telegram first.');
        return 1;
    }
    $contacts = \App\Models\CustomerContact::where('type', 'telegram')
        ->whereNotNull('value')->where('value', '!=', '')
        ->whereNotNull('customer_id')
        ->get()
        ->filter(fn ($c) => ctype_digit((string) trim($c->value)));
    $total = $contacts->pluck('customer_id')->unique()->count();
    $this->info("Found $total contacts to sync.");
    if ($total === 0) {
        $this->warn('No numeric Telegram IDs found. Sync skipped.');
        return 0;
    }
    $this->info('Running TelegramSyncContactsJob...');
    $syncId = \Illuminate\Support\Str::uuid()->toString();
    \Illuminate\Support\Facades\Cache::put('telegram_sync_' . $syncId, [
        'status' => 'running',
        'processed' => 0,
        'updated' => 0,
        'total' => null,
        'failed' => 0,
    ], now()->addHours(24));
    try {
        \App\Jobs\TelegramSyncContactsJob::dispatchSync($syncId);
        $data = \Illuminate\Support\Facades\Cache::get('telegram_sync_' . $syncId);
        if (($data['status'] ?? '') === 'error') {
            $this->error('Sync failed: ' . ($data['error'] ?? 'Unknown'));
            return 1;
        }
        $this->info('Done. Processed: ' . ($data['processed'] ?? 0) . ', Updated: ' . ($data['updated'] ?? 0) . ', Failed: ' . ($data['failed'] ?? 0));
    } catch (\Throwable $e) {
        $this->error('Failed: ' . $e->getMessage());
        throw $e;
    }
    return 0;
})->purpose('Sync contact info from Telegram (run manually when queue does not work)');

Artisan::command('telegram:diag {--deep : Run a deeper Madeline connectivity check}', function () {
    $this->info('Telegram Diagnostics');
    $this->line(str_repeat('-', 72));

    $conn = \App\Models\TelegramUserConnection::getActive();
    if (! $conn) {
        $this->error('Active connection: NOT FOUND');
        $this->line('Hint: connect Telegram account first in Settings.');
        return 1;
    }

    $this->info('Active connection');
    $this->line(' - id: '.$conn->id);
    $this->line(' - status: '.($conn->status ?? 'unknown'));
    $this->line(' - user_id: '.($conn->user_id ?? 'null'));
    $this->line(' - phone: '.($conn->phone ?? 'null'));
    $this->line(' - username: '.($conn->telegram_username ?? 'null'));
    $this->line(' - last_used_at: '.($conn->last_used_at?->toDateTimeString() ?? 'null'));

    $sessionPath = $conn->getSessionPath();
    $sessionExists = is_dir($sessionPath) || file_exists($sessionPath);
    $this->line(' - session_path: '.$sessionPath);
    $this->line(' - session_exists: '.($sessionExists ? 'yes' : 'no'));

    $marker = \App\Services\MadelineProtoService::daemonListenMarkerPath($conn);
    $markerExists = is_file($marker);
    $markerPid = $markerExists ? (int) trim((string) @file_get_contents($marker)) : 0;
    $markerRunning = false;
    if ($markerPid > 0) {
        if (function_exists('posix_kill') && extension_loaded('posix')) {
            $markerRunning = @posix_kill($markerPid, 0);
        } elseif (PHP_OS_FAMILY === 'Linux' && is_dir('/proc/'.$markerPid)) {
            $markerRunning = true;
        }
    }

    $this->info('Listener marker');
    $this->line(' - marker_file: '.$marker);
    $this->line(' - marker_exists: '.($markerExists ? 'yes' : 'no'));
    $this->line(' - marker_pid: '.($markerPid ?: 'n/a'));
    $this->line(' - marker_pid_running: '.($markerRunning ? 'yes' : 'no'));
    $this->line(' - is_listen_daemon_active(): '.(\App\Services\MadelineProtoService::isListenDaemonActive($conn) ? 'yes' : 'no'));

    $lockKey = 'madeline_session_'.$conn->id;
    $lock = Cache::lock($lockKey, 15);
    $lockAcquired = $lock->get();
    if ($lockAcquired) {
        $lock->release();
    }
    $this->info('Cache lock');
    $this->line(' - key: '.$lockKey);
    $this->line(' - immediate_acquire: '.($lockAcquired ? 'yes' : 'no (held by another operation)'));

    $this->info('Environment');
    $this->line(' - app_env: '.config('app.env'));
    $this->line(' - app_debug: '.(config('app.debug') ? 'true' : 'false'));
    $this->line(' - php_sapi: '.PHP_SAPI);
    $this->line(' - running_in_console: '.(app()->runningInConsole() ? 'yes' : 'no'));
    $this->line(' - MADELINE_PROTO_FORCE_FULL: '.(config('services.telegram.madeline_force_full_instance') ? 'true' : 'false'));
    $this->line(' - MADELINE_PROTO_RUN_TIMEOUT: '.config('services.telegram.madeline_run_timeout'));
    $this->line(' - MADELINE_PROTO_CACHE_LOCK_BLOCK: '.config('services.telegram.madeline_cache_lock_block'));

    $this->info('User processes (telegram/queue/schedule)');
    $currentUser = get_current_user();
    $processOutput = '';
    if (function_exists('shell_exec')) {
        $cmd = 'ps -fu '.escapeshellarg($currentUser).' | grep -E "artisan|php-fpm|telegram|queue:work|schedule:run" | grep -v grep';
        $processOutput = (string) shell_exec($cmd);
    }
    if (trim($processOutput) === '') {
        $this->line(' - no matching processes found (or shell_exec disabled)');
    } else {
        $fetchCount = 0;
        $queueCount = 0;
        foreach (preg_split('/\r\n|\r|\n/', trim($processOutput)) as $line) {
            $this->line(' - '.$line);
            if (str_contains($line, 'artisan telegram:fetch-incoming')) {
                $fetchCount++;
            }
            if (str_contains($line, 'artisan queue:work')) {
                $queueCount++;
            }
        }

        if ($fetchCount > 1) {
            $this->warn("⚠ Detected {$fetchCount} telegram:fetch-incoming processes in parallel. This usually breaks incoming sync.");
        }
        if ($queueCount > 1) {
            $this->warn("⚠ Detected {$queueCount} queue:work processes. Ensure this is intentional and not duplicated by cron.");
        }
    }

    if ($this->option('deep')) {
        $this->line(str_repeat('-', 72));
        $this->info('Deep check');
        $started = microtime(true);
        try {
            $service = new \App\Services\MadelineProtoService($conn);
            $dialogs = $service->getDialogs();
            $elapsed = round((microtime(true) - $started) * 1000);
            $this->line(' - getDialogs: OK (count='.count($dialogs).", {$elapsed}ms)");
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $started) * 1000);
            $this->error(' - getDialogs: FAILED after '.$elapsed.'ms');
            $this->line('   class: '.get_class($e));
            $this->line('   msg: '.($e->getMessage() ?: '(empty)'));
        }
    }

    $this->line(str_repeat('-', 72));
    $this->comment('Tip: run `php artisan telegram:diag --deep` when an issue happens.');
    return 0;
})->purpose('Diagnose Telegram Madeline session/locks/process state');

Artisan::command('telegram:unlock-locks {--conn= : Connection ID, defaults to active}', function () {
    $connId = (int) ($this->option('conn') ?: 0);
    $conn = $connId > 0
        ? \App\Models\TelegramUserConnection::find($connId)
        : \App\Models\TelegramUserConnection::getActive();

    if (! $conn) {
        $this->error('No connection found to unlock.');
        return 1;
    }

    $sessionLockKey = 'madeline_session_'.$conn->id;
    Cache::lock($sessionLockKey)->forceRelease();
    $this->info("Force released cache lock: {$sessionLockKey}");

    Cache::lock('telegram_fetch_incoming_command_lock')->forceRelease();
    $this->info('Force released legacy command lock: telegram_fetch_incoming_command_lock');

    $fileLock = storage_path('framework/telegram_fetch_incoming.lock');
    if (is_file($fileLock)) {
        @unlink($fileLock);
        $this->info("Removed file lock: {$fileLock}");
    } else {
        $this->line("File lock not found: {$fileLock}");
    }

    $this->comment('Done. If a live process still holds flock, it will recreate/relock while running.');
    return 0;
})->purpose('Force release Telegram fetch/session locks');

Artisan::command('org:fix-superadmin-memberships {--user= : User id/email/username (optional)}', function () {
    $needle = (string) ($this->option('user') ?? '');

    $q = \App\Models\User::query();
    if ($needle !== '') {
        if (ctype_digit($needle)) {
            $q->where('id', (int) $needle);
        } else {
            $q->where(function ($qq) use ($needle) {
                $qq->where('email', $needle)->orWhere('username', $needle);
            });
        }
    } else {
        $q->whereHas('roles', fn ($r) => $r->where('name', 'super_admin'));
    }

    $users = $q->get();
    if ($users->isEmpty()) {
        $this->warn('No matching super_admin users found.');
        return 0;
    }

    $totalDetached = 0;
    foreach ($users as $user) {
        $keepOrgIds = collect();
        if ($user->current_organization_id) {
            $keepOrgIds->push((int) $user->current_organization_id);
        }

        $ownedOrgIds = \App\Models\Organization::query()
            ->where('owner_user_id', $user->id)
            ->pluck('id');
        $keepOrgIds = $keepOrgIds->merge($ownedOrgIds);

        $defaultOrgId = \Illuminate\Support\Facades\DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_default', true)
            ->value('organization_id');
        if ($defaultOrgId) {
            $keepOrgIds->push((int) $defaultOrgId);
        }

        $keepOrgIds = $keepOrgIds->filter()->unique()->values();

        $currentOrgIds = $user->organizations()->pluck('organizations.id');
        $detachOrgIds = $currentOrgIds->diff($keepOrgIds);

        if ($detachOrgIds->isNotEmpty()) {
            $user->organizations()->detach($detachOrgIds->all());
            $totalDetached += $detachOrgIds->count();
            $this->line("User {$user->id} ({$user->email}) detached from org ids: ".$detachOrgIds->implode(', '));
        } else {
            $this->line("User {$user->id} ({$user->email}) has no extra org memberships.");
        }
    }

    $this->info("Done. Detached memberships: {$totalDetached}");
    return 0;
})->purpose('Remove unintended org memberships from super_admin users');

Artisan::command('debug:user-orgs {needle : User id/email/username}', function () {
    $needle = (string) $this->argument('needle');
    $u = \App\Models\User::query()
        ->when(ctype_digit($needle), fn ($q) => $q->where('id', (int) $needle))
        ->when(! ctype_digit($needle), fn ($q) => $q->where('email', $needle)->orWhere('username', $needle))
        ->first();

    if (! $u) {
        $this->error('User not found.');
        return 1;
    }

    $this->info("User {$u->id}: {$u->name} <{$u->email}>");
    $this->line('roles: '.$u->getRoleNames()->implode(','));
    $this->line('current_organization_id: '.($u->current_organization_id ?: 'null'));

    $orgs = $u->organizations()
        ->orderBy('name')
        ->get(['organizations.id', 'organizations.name', 'organizations.slug', 'organization_user.role_in_org', 'organization_user.status', 'organization_user.is_default']);

    foreach ($orgs as $o) {
        $this->line(" - org {$o->id}: {$o->name} ({$o->slug}) role_in_org={$o->pivot?->role_in_org} status={$o->pivot?->status} default=".((bool) ($o->pivot?->is_default ?? false) ? '1' : '0'));
    }

    return 0;
})->purpose('Print a user roles/org memberships');

Artisan::command('debug:find-user {q : Search by name/email/username contains}', function () {
    $q = (string) $this->argument('q');
    $users = \App\Models\User::query()
        ->where('name', 'like', '%'.$q.'%')
        ->orWhere('email', 'like', '%'.$q.'%')
        ->orWhere('username', 'like', '%'.$q.'%')
        ->orderBy('id')
        ->limit(20)
        ->get(['id', 'name', 'email', 'username']);

    if ($users->isEmpty()) {
        $this->warn('No users found.');
        return 0;
    }

    foreach ($users as $u) {
        $this->line("{$u->id}\t{$u->username}\t{$u->email}\t{$u->name}");
    }
    return 0;
})->purpose('Find users by substring');

Artisan::command('user:promote-superadmin {needle : User id/email/username}', function () {
    $needle = (string) $this->argument('needle');
    $u = \App\Models\User::query()
        ->when(ctype_digit($needle), fn ($q) => $q->where('id', (int) $needle))
        ->when(! ctype_digit($needle), fn ($q) => $q->where('email', $needle)->orWhere('username', $needle))
        ->first();

    if (! $u) {
        $this->error('User not found.');
        return 1;
    }

    if (! $u->hasRole('super_admin')) {
        $u->assignRole('super_admin');
    }
    $this->info("User {$u->id} promoted: roles=".$u->getRoleNames()->implode(','));
    return 0;
})->purpose('Assign super_admin role to a user');

Artisan::command('whatsapp:fix-lid-senders {--dry-run : Only report changes, do not update database}', function () {
    $dryRun = (bool) $this->option('dry-run');

    $this->info('Fix WhatsApp inbound LID senders (15-digit from_phone)');
    $this->line(' - dry_run: '.($dryRun ? 'yes' : 'no'));

    $extractDigits = function (?string $value): string {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '@')) {
            $value = explode('@', $value, 2)[0] ?? $value;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        return $digits;
    };

    $pickBest = function (array $candidates) {
        $candidates = array_values(array_filter($candidates, fn ($d) => is_string($d) && $d !== ''));
        if ($candidates === []) {
            return '';
        }
        usort($candidates, function (string $a, string $b) {
            $score = function (int $len): int {
                if ($len >= 10 && $len <= 14) {
                    return 0;
                }
                if ($len >= 8 && $len <= 9) {
                    return 1;
                }
                if ($len === 15) {
                    return 3;
                }
                return 2;
            };
            $sa = $score(strlen($a));
            $sb = $score(strlen($b));
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strlen($a) <=> strlen($b);
        });
        return $candidates[0] ?? '';
    };

    $q = \App\Models\WhatsAppMessage::withoutGlobalScope('organization')
        ->where('direction', 'incoming')
        ->whereNotNull('from_phone')
        ->whereRaw('CHAR_LENGTH(from_phone) = 15');

    $total = (int) $q->count();
    $this->info("Found {$total} messages to inspect.");
    if ($total === 0) {
        $this->comment('Nothing to do.');
        return 0;
    }

    $updated = 0;
    $skipped = 0;
    $failed = 0;

    $q->orderBy('id')->chunkById(200, function ($rows) use ($dryRun, $extractDigits, $pickBest, &$updated, &$skipped, &$failed) {
        /** @var \App\Models\WhatsAppMessage $row */
        foreach ($rows as $row) {
            try {
                $meta = is_array($row->metadata) ? $row->metadata : [];
                $payload = is_array($meta['payload'] ?? null) ? ($meta['payload'] ?? []) : [];
                $data0 = null;
                if (isset($payload['data'][0]) && is_array($payload['data'][0])) {
                    $data0 = $payload['data'][0];
                }

                $remoteJid = is_array($data0) ? (($data0['key']['remoteJid'] ?? null) ?: null) : null;
                $participant = is_array($data0) ? (($data0['key']['participant'] ?? null) ?: null) : null;
                $remoteJidAlt = is_array($data0) ? (($data0['key']['remoteJidAlt'] ?? null) ?: null) : null;

                $candidates = [
                    $extractDigits(is_string($remoteJid) ? $remoteJid : null),
                    $extractDigits(is_string($participant) ? $participant : null),
                    $extractDigits(is_string($remoteJidAlt) ? $remoteJidAlt : null),
                ];
                $best = $pickBest($candidates);

                // Only update if we found a phone-like replacement.
                if ($best === '' || strlen($best) === 15) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("would_update\tid={$row->id}\torg={$row->organization_id}\tfrom={$row->from_phone}\t=>\t{$best}");
                    $updated++;
                    continue;
                }

                $row->from_phone = $best;
                $row->save();
                $updated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("failed\tid={$row->id}\t".$e->getMessage());
            }
        }
    });

    $this->info('Done.');
    $this->line(" - updated: {$updated}");
    $this->line(" - skipped: {$skipped}");
    $this->line(" - failed: {$failed}");
    if ($dryRun) {
        $this->comment('Tip: rerun without --dry-run to apply changes.');
    }
    return $failed > 0 ? 1 : 0;
})->purpose('Fix inbound WhatsApp messages where from_phone was saved as 15-digit LID (use metadata.remoteJid)');
