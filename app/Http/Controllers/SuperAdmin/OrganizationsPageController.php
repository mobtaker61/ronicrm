<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationsPageController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->isSuperAdmin()) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Organizations/Index', [
            'organizations' => Organization::query()
                ->with([
                    'users' => fn ($q) => $q->orderBy('name'),
                    'owner',
                    'languages' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
                ])
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn ($organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'is_active' => (bool) $organization->is_active,
                    'owner_user_id' => $organization->owner_user_id,
                    'owner_name' => $organization->owner?->name,
                    'users_count' => $organization->users_count,
                    'language_ids' => $organization->languages->pluck('id')->values()->all(),
                    'members' => $organization->users->map(fn ($member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role_in_org' => $member->pivot?->role_in_org,
                        'status' => $member->pivot?->status,
                        'is_default' => (bool) ($member->pivot?->is_default ?? false),
                    ])->values(),
                ]),
            'current_user_id' => Auth::id(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'all_languages' => Language::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }
}

