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
            \App\Http\Middleware\HandleInertiaRequests::class,
            // بعد از کنترلر: commit تراکنش یتیم PDO تا CRUD در دیتابیس پایدار شود
            \App\Http\Middleware\CommitOrphanPdoTransaction::class,
        ]);
        $middleware->redirectGuestsTo('/login');

        // Webhooks receive POST from external services (no CSRF token)
        $middleware->validateCsrfTokens(except: [
            'telegram-webhook',
            'wpwebhook',
            'instagram-webhook',
        ]);

        // Trust all proxies (needed for ngrok)
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('campaigns:process')->everyMinute();
        // وقتی telegram:listen-incoming (daemon) اجرا نمی‌شود، با polling پیام‌های DM را می‌گیرد.
        // هر دو از یک session MadelineProto استفاده می‌کنند — همزمان اجرا نکنید.
        $schedule->command('telegram:fetch-incoming')->everyThreeMinutes()->withoutOverlapping(25);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
