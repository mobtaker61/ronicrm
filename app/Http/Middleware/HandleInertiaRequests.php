<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        // Return a static version based on manifest file hash instead of Git
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            return md5_file($manifestPath);
        }

        return null;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'i18n' => [
                'locale' => fn () => app()->getLocale(),
                'default_locale' => fn () => (string) (\App\Models\Language::query()
                    ->where('is_active', true)
                    ->where('is_default', true)
                    ->value('code') ?: config('app.locale', 'en')),
                'json_url' => fn () => route('i18n.json', ['locale' => app()->getLocale()]),
            ],
            'html' => [
                'lang' => fn () => str_replace('_', '-', app()->getLocale()),
                'dir' => function () {
                    $code = app()->getLocale();
                    $direction = \App\Models\Language::query()
                        ->where('code', $code)
                        ->value('direction');

                    return $direction === 'rtl' ? 'rtl' : 'ltr';
                },
            ],
            'publicLocales' => fn () => \App\Models\Language::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn ($l) => ['code' => (string) $l->code, 'name' => (string) $l->name])
                ->values()
                ->all(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'google_sync_errors' => fn () => $request->session()->get('google_sync_errors'),
            ],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar_url' => $request->user()->avatar_path ? Storage::url($request->user()->avatar_path) : null,
                    'current_organization_id' => $request->user()->current_organization_id,
                    'roles' => $request->user()->getRoleNames(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'organizations' => fn () => (function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return [];
                }

                $q = $user->organizations()->orderBy('name');

                // سوپرادمین‌ها گاهی به اشتباه به چند سازمان عضو می‌شوند؛
                // برای اینکه UI سازمان‌های نامرتبط را نشان ندهد، فقط سازمان پیش‌فرض/فعلی را نمایش می‌دهیم.
                if ($user->isSuperAdmin()) {
                    $currentOrgId = (int) ($user->current_organization_id ?: 0);
                    $q->where(function ($b) use ($currentOrgId) {
                        // توجه: داخل closure، Builder خام داریم و wherePivot در دسترس نیست.
                        $b->where('organization_user.is_default', true);
                        if ($currentOrgId > 0) {
                            $b->orWhere('organizations.id', $currentOrgId);
                        }
                    });
                } else {
                    $q->wherePivot('status', 'active');
                }

                return $q->get(['organizations.id', 'organizations.name', 'organizations.slug'])
                    ->map(fn ($organization) => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                        'role_in_org' => $organization->pivot?->role_in_org,
                        'status' => $organization->pivot?->status,
                        'is_default' => (bool) ($organization->pivot?->is_default ?? false),
                    ])
                    ->values();
            })(),
            'currentOrganization' => fn () => (function () use ($request) {
                $org = $request->user()?->currentOrganization;
                if (! $org) {
                    return null;
                }

                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'legal_name' => $org->legal_name,
                    'logo_url' => $org->logo_path ? \Illuminate\Support\Facades\Storage::url($org->logo_path) : null,
                ];
            })(),
            'currentOrganizationRole' => fn () => $request->user()
                ? $request->user()->organizations()
                    ->where('organizations.id', $request->user()->current_organization_id)
                    ->first()?->pivot?->role_in_org
                : null,
            'canManageOrganizationSettings' => fn () => (bool) ($request->user()?->canManageOrganizationSettings()),
            'userManagementScope' => fn () => (function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return 'none';
                }
                if ($user->hasGlobalAdminAccess()) {
                    return 'global';
                }
                if ($user->canManageOrganizationSettings() && $user->current_organization_id) {
                    return 'organization';
                }

                return 'none';
            })(),
            'languages' => fn () => $request->user()
                ? (function () use ($request) {
                    $q = \App\Models\Language::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name');
                    $orgId = $request->user()?->current_organization_id;
                    \App\Services\OrganizationLanguageScope::restrictQuery($q, $orgId ? (int) $orgId : null);

                    return $q->get(['id', 'code', 'name', 'is_default', 'direction', 'font_family']);
                })()
                : [],
            'telegramGroupCategories' => fn () => $request->user() ? \App\Models\TelegramGroupCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : [],
            'csrf_token' => csrf_token(),
            'subscription' => fn () => (function () use ($request) {
                $orgId = \App\Support\OrganizationContext::getOrganizationId();
                if (! $request->user() || ! $orgId) {
                    return null;
                }
                $service = app(\App\Services\SubscriptionService::class);
                $sub = $service->getOrCreateForOrganization((int) $orgId);
                $status = $service->computeStatus($sub);

                return [
                    'status' => $status,
                    'remaining_days' => $service->remainingDays($sub),
                    'ends_at' => $sub->ends_at?->toIso8601String(),
                    'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
                    'grace_ends_at' => $sub->grace_ends_at?->toIso8601String(),
                ];
            })(),
        ];
    }
}
