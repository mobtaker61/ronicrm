<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::get('/ping', fn () => response('OK', 200));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentOrganization::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            // بعد از کنترلر: commit تراکنش یتیم PDO تا CRUD در دیتابیس پایدار شود
            \App\Http\Middleware\CommitOrphanPdoTransaction::class,
        ]);
        $middleware->redirectGuestsTo('/login');

        // Webhooks receive POST from external services (no CSRF token)
        $middleware->validateCsrfTokens(except: [
            'telegram-webhook',
            'telegram-webhook/*',
            'wpwebhook',
            'instagram-webhook',
            'instagram-webhook/*',
        ]);

        // Trust all proxies (needed for ngrok)
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('campaigns:process')->everyMinute();
        $schedule->command('telegram:process-scheduled-sends')->everyMinute();
        // وقتی telegram:listen-incoming روشن است، polling خاموش می‌شود (دریافت از EventHandler).
        $schedule->command('telegram:fetch-incoming')
            ->everyMinute()
            ->withoutOverlapping(25)
            ->when(function (): bool {
                if (! filter_var(env('TELEGRAM_FETCH_INCOMING_SCHEDULED', true), FILTER_VALIDATE_BOOL)) {
                    return false;
                }
                $conn = \App\Models\TelegramUserConnection::getActive();
                if (! $conn) {
                    return true;
                }

                return ! \App\Services\MadelineProtoService::isListenDaemonActive($conn);
            });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
