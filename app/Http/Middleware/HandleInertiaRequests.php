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
            'organizations' => fn () => $request->user()
                ? $request->user()->organizations()
                    ->orderBy('name')
                    ->get(['organizations.id', 'organizations.name', 'organizations.slug'])
                    ->map(fn ($organization) => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                        'role_in_org' => $organization->pivot?->role_in_org,
                        'status' => $organization->pivot?->status,
                        'is_default' => (bool) ($organization->pivot?->is_default ?? false),
                    ])
                    ->values()
                : [],
            'currentOrganization' => fn () => $request->user()?->currentOrganization?->only(['id', 'name', 'slug']),
            'currentOrganizationRole' => fn () => $request->user()
                ? $request->user()->organizations()
                    ->where('organizations.id', $request->user()->current_organization_id)
                    ->first()?->pivot?->role_in_org
                : null,
            'languages' => fn () => $request->user() ? \App\Models\Language::orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']) : [],
            'telegramGroupCategories' => fn () => $request->user() ? \App\Models\TelegramGroupCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']) : [],
            'csrf_token' => csrf_token(),
        ];
    }
}
