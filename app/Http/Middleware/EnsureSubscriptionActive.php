<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // اشتراک فقط برای کاربران واردشده معنی دارد؛ مهمان‌ها نباید به سازمان پیش‌فرض دیتابیس قاطی شوند.
        if (! $request->user()) {
            return $next($request);
        }

        // فرانت عمومی، تمدید/تنظیمات، خروج و زبان بدون قفل اشتراک
        if ($request->routeIs([
            'front.*',
            'settings.*',
            'logout',
            'locale.set',
            'i18n.json',
            'i18n.locale.set',
            'public.*',
        ])) {
            return $next($request);
        }

        $orgId = OrganizationContext::getOrganizationId();
        if (! $orgId) {
            return $next($request);
        }

        $sub = app(SubscriptionService::class)->getOrCreateForOrganization((int) $orgId);
        if (app(SubscriptionService::class)->isActive($sub)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'error' => 'subscription_expired'], 402);
        }

        return redirect()
            ->route('settings.index', ['tab' => 'organization'])
            ->with('error', 'اشتراک سازمان شما منقضی شده است. لطفاً اشتراک را تمدید کنید یا با پشتیبانی تماس بگیرید.');
    }
}
