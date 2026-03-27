<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if ($user->hasGlobalAdminAccess()) {
                return $next($request);
            }
            if ($user->canManageOrganizationSettings() && $user->current_organization_id) {
                return $next($request);
            }

            abort(403, 'Unauthorized action.');
        });
    }

    protected function isOrganizationScoped(): bool
    {
        return ! Auth::user()->hasGlobalAdminAccess()
            && Auth::user()->canManageOrganizationSettings()
            && (bool) Auth::user()->current_organization_id;
    }

    protected function currentOrganizationId(): ?int
    {
        $id = Auth::user()->current_organization_id;

        return $id ? (int) $id : null;
    }

    protected function ensureMemberOfCurrentOrganization(User $user): void
    {
        $orgId = $this->currentOrganizationId();
        if (! $orgId) {
            abort(403);
        }
        if (! $user->organizations()->where('organizations.id', $orgId)->exists()) {
            abort(403);
        }
    }

    /**
     * Display a listing of users
     */
    public function index(): Response
    {
        if ($this->isOrganizationScoped()) {
            $orgId = $this->currentOrganizationId();
            $organization = Organization::query()
                ->with(['users' => fn ($q) => $q->orderBy('name')])
                ->findOrFail($orgId);

            $users = $organization->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => [],
                    'role_in_org' => $user->pivot?->role_in_org,
                    'status' => $user->pivot?->status,
                    'is_default' => (bool) ($user->pivot?->is_default ?? false),
                    'created_at' => $user->created_at,
                    'avatar_url' => $user->avatar_path ? \Illuminate\Support\Facades\Storage::url($user->avatar_path) : null,
                ];
            });

            return Inertia::render('Settings/Users/Index', [
                'users' => $users,
                'roles' => [],
                'userManagementScope' => 'organization',
            ]);
        }

        $users = User::with('roles')->orderBy('name')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->toArray(),
                'created_at' => $user->created_at,
            ];
        });

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'roles' => \Spatie\Permission\Models\Role::orderBy('name')->get(['id', 'name']),
            'userManagementScope' => 'global',
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        if ($this->isOrganizationScoped()) {
            $orgId = $this->currentOrganizationId();
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', 'unique:users,username'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'role_in_org' => ['required', 'string', 'in:org_admin,org_manager,org_agent'],
                'status' => ['nullable', 'string', 'in:active,inactive'],
                'is_default' => ['nullable', 'boolean'],
            ]);

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

            $isDefault = (bool) ($validated['is_default'] ?? false);
            if ($isDefault) {
                \Illuminate\Support\Facades\DB::table('organization_user')->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $organization = Organization::query()->findOrFail($orgId);
            $organization->users()->attach($user->id, [
                'role_in_org' => $validated['role_in_org'],
                'status' => $validated['status'] ?? 'active',
                'is_default' => $isDefault,
            ]);

            return redirect()->route('settings.users.index')
                ->with('success', 'User created successfully.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'current_organization_id' => auth()->user()->current_organization_id,
        ]);

        if (auth()->user()->current_organization_id) {
            $user->organizations()->syncWithoutDetaching([
                auth()->user()->current_organization_id => [
                    'role_in_org' => 'org_agent',
                    'is_default' => true,
                    'status' => 'active',
                ],
            ]);
        }

        if (! empty($validated['roles'])) {
            $user->assignRole($validated['roles']);
        } else {
            $user->assignRole('user');
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        if ($this->isOrganizationScoped()) {
            $this->ensureMemberOfCurrentOrganization($user);
            $orgId = $this->currentOrganizationId();

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
                'role_in_org' => ['required', 'string', 'in:org_admin,org_manager,org_agent'],
                'status' => ['required', 'string', 'in:active,inactive'],
                'is_default' => ['nullable', 'boolean'],
            ]);

            $user->update([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
            ]);

            if (! empty($validated['password'])) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }

            $isDefault = (bool) ($validated['is_default'] ?? false);
            if ($isDefault) {
                \Illuminate\Support\Facades\DB::table('organization_user')->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $organization = Organization::query()->findOrFail($orgId);
            $organization->users()->updateExistingPivot($user->id, [
                'role_in_org' => $validated['role_in_org'],
                'status' => $validated['status'],
                'is_default' => $isDefault,
            ]);

            if ($user->current_organization_id === $orgId && $validated['status'] === 'inactive') {
                $nextOrganizationId = \Illuminate\Support\Facades\DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->where('organization_id', '!=', $orgId)
                    ->orderByDesc('is_default')
                    ->orderBy('organization_id')
                    ->value('organization_id');

                $user->update(['current_organization_id' => $nextOrganizationId ?: null]);
            }

            return redirect()->route('settings.users.index')
                ->with('success', 'User updated successfully.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('settings.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()
                ->with('error', 'You cannot delete your own account.');
        }

        if ($this->isOrganizationScoped()) {
            $this->ensureMemberOfCurrentOrganization($user);
            $orgId = $this->currentOrganizationId();
            $organization = Organization::query()->findOrFail($orgId);

            if ($user->hasGlobalAdminAccess()) {
                return redirect()->back()
                    ->with('error', 'Cannot remove platform administrators from the organization.');
            }

            if ((int) $organization->owner_user_id === (int) $user->id) {
                return redirect()->back()
                    ->with('error', 'Cannot remove the organization owner from the organization.');
            }

            $organization->users()->detach($user->id);

            $otherCount = $user->organizations()->count();
            if ($otherCount === 0) {
                $user->delete();
            } elseif ($user->current_organization_id === $orgId) {
                $nextOrganizationId = \Illuminate\Support\Facades\DB::table('organization_user')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->orderByDesc('is_default')
                    ->orderBy('organization_id')
                    ->value('organization_id');

                $user->update(['current_organization_id' => $nextOrganizationId ?: null]);
            }

            return redirect()->route('settings.users.index')
                ->with('success', 'User removed from organization.');
        }

        $user->delete();

        return redirect()->route('settings.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
