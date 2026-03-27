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
            ->route('settings.index')
            ->with('error', 'اشتراک سازمان شما منقضی شده است. لطفاً با پشتیبانی تماس بگیرید.');
    }
}

