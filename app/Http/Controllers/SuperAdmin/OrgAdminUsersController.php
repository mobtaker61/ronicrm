<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrgAdminUsersController extends Controller
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
        $orgs = Organization::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->values();

        $users = User::query()
            ->whereHas('organizations', fn ($q) => $q->where('organization_user.role_in_org', 'org_admin'))
            ->with(['organizations' => fn ($q) => $q
                ->where('organization_user.role_in_org', 'org_admin')
                ->orderBy('name')
                ->get(['organizations.id', 'organizations.name', 'organizations.slug'])])
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'current_organization_id' => $u->current_organization_id,
                'roles' => $u->roles->pluck('name')->values()->all(),
                'organizations' => $u->organizations->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'slug' => $o->slug,
                    'status' => $o->pivot?->status,
                    'is_default' => (bool) ($o->pivot?->is_default ?? false),
                ])->values()->all(),
            ])
            ->values();

        return Inertia::render('SuperAdmin/AdminUsers/Index', [
            'users' => $users,
            'organizations' => $orgs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $orgId = (int) $validated['organization_id'];

        return DB::transaction(function () use ($validated, $orgId) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'current_organization_id' => $orgId,
            ]);

            if (! $user->hasRole('user')) {
                $user->assignRole('user');
            }

            DB::table('organization_user')->where('user_id', $user->id)->update(['is_default' => false]);
            $user->organizations()->syncWithoutDetaching([
                $orgId => [
                    'role_in_org' => 'org_admin',
                    'status' => $validated['status'] ?? 'active',
                    'is_default' => true,
                ],
            ]);

            $org = Organization::query()->find($orgId);
            if ($org && ! $org->owner_user_id) {
                $org->update(['owner_user_id' => $user->id]);
            }

            return redirect()->back()->with('success', 'Organization admin created successfully.');
        });
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
            'set_as_owner' => ['nullable', 'boolean'],
        ]);

        $orgId = (int) $validated['organization_id'];
        $isDefault = (bool) ($validated['is_default'] ?? false);

        return DB::transaction(function () use ($user, $validated, $orgId, $isDefault) {
            $user->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
            ]);

            if (! empty($validated['password'])) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            if ($isDefault) {
                DB::table('organization_user')->where('user_id', $user->id)->update(['is_default' => false]);
                $user->update(['current_organization_id' => $orgId]);
            }

            $user->organizations()->syncWithoutDetaching([
                $orgId => [
                    'role_in_org' => 'org_admin',
                    'status' => $validated['status'],
                    'is_default' => $isDefault,
                ],
            ]);

            if ((bool) ($validated['set_as_owner'] ?? false)) {
                Organization::query()->where('id', $orgId)->update(['owner_user_id' => $user->id]);
            }

            return redirect()->back()->with('success', 'Organization admin updated successfully.');
        });
    }

    public function removeFromOrganization(Request $request, User $user)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);
        $orgId = (int) $validated['organization_id'];

        $user->organizations()->detach($orgId);

        if ($user->current_organization_id === $orgId) {
            $nextOrgId = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('organization_id')
                ->value('organization_id');
            $user->update(['current_organization_id' => $nextOrgId ?: null]);
        }

        return redirect()->back()->with('success', 'Admin removed from organization.');
    }
}

