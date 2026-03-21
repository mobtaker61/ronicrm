<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * در PHP 8.4 + PDO/mysql گاهی PDO::inTransaction() true است اما Laravel::transactionLevel() صفر.
 * در این حالت DELETE/INSERT/UPDATE در همان اتصال دیده می‌شود ولی commit نمی‌شود و با پایان درخواست rollback می‌شود
 * (پیام موفقیت می‌آید اما دیتابیس تغییر نمی‌کند، یا ریدایرکت به رکورد حذف‌شده → 404).
 */
class CommitOrphanPdoTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        // قبل از کنترلر: اتصال پایدار (مثلاً Octane) ممکن است تراکنش یتیم از درخواست قبلی داشته باشد.
        $this->commitOrphanIfNeeded(DB::connection());

        $response = $next($request);

        $this->commitOrphanIfNeeded(DB::connection());

        return $response;
    }

    protected function commitOrphanIfNeeded(\Illuminate\Database\Connection $connection): void
    {
        try {
            $pdo = $connection->getPdo();
        } catch (\Throwable) {
            return;
        }

        if (! $pdo->inTransaction()) {
            return;
        }

        if ($connection->transactionLevel() > 0) {
            return;
        }

        try {
            $pdo->commit();
        } catch (\Throwable) {
            // اگر تراکنشی نبود، برخی درایورها خطا می‌دهند
        }
    }
}
