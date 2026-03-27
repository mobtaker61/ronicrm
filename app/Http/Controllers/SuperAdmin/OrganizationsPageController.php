<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
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
                ->with(['users' => fn ($q) => $q->orderBy('name')])
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn ($organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'is_active' => (bool) $organization->is_active,
                    'users_count' => $organization->users_count,
                    'members' => $organization->users->map(fn ($member) => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role_in_org' => $member->pivot?->role_in_org,
                        'status' => $member->pivot?->status,
                        'is_default' => (bool) ($member->pivot?->is_default ?? false),
                    ])->values(),
                ]),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}

