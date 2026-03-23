<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * @var array<int, string>
     */
    protected array $organizationRoles = ['org_admin', 'org_manager', 'org_agent'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()?->hasRole('super_admin')) {
                abort(403, 'Only super administrators can manage organizations.');
            }

            return $next($request);
        });
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:organizations,slug'],
            'is_active' => ['boolean'],
        ]);

        Organization::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'owner_user_id' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Organization created successfully.');
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:organizations,slug,'.$organization->getKey()],
            'is_active' => ['boolean'],
        ]);

        $organization->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->back()->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        if ($organization->slug === 'roni-plus') {
            return redirect()->back()->with('error', 'Default organization cannot be deleted.');
        }

        $organization->delete();

        return redirect()->back()->with('success', 'Organization deleted successfully.');
    }

    public function addMember(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_in_org' => ['required', 'string', 'in:'.implode(',', $this->organizationRoles)],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $userId = (int) $validated['user_id'];
        $isDefault = (bool) ($validated['is_default'] ?? false);
        $status = (string) ($validated['status'] ?? 'active');

        if ($isDefault) {
            DB::table('organization_user')->where('user_id', $userId)->update(['is_default' => false]);
        }

        $organization->users()->syncWithoutDetaching([
            $userId => [
                'role_in_org' => $validated['role_in_org'],
                'status' => $status,
                'is_default' => $isDefault,
            ],
        ]);

        $user = User::query()->find($userId);
        if ($user && ! $user->current_organization_id) {
            $user->update(['current_organization_id' => $organization->id]);
        }

        return redirect()->back()->with('success', 'Organization member added successfully.');
    }

    public function updateMember(Request $request, Organization $organization, User $user)
    {
        $validated = $request->validate([
            'role_in_org' => ['required', 'string', 'in:'.implode(',', $this->organizationRoles)],
            'status' => ['required', 'string', 'in:active,inactive'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $exists = $organization->users()->where('users.id', $user->id)->exists();
        if (! $exists) {
            abort(404, 'Member not found in organization.');
        }

        $isDefault = (bool) ($validated['is_default'] ?? false);
        if ($isDefault) {
            DB::table('organization_user')->where('user_id', $user->id)->update(['is_default' => false]);
        }

        $organization->users()->updateExistingPivot($user->id, [
            'role_in_org' => $validated['role_in_org'],
            'status' => $validated['status'],
            'is_default' => $isDefault,
        ]);

        if ($user->current_organization_id === $organization->id && $validated['status'] === 'inactive') {
            $nextOrganizationId = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('organization_id', '!=', $organization->id)
                ->orderByDesc('is_default')
                ->orderBy('organization_id')
                ->value('organization_id');

            $user->update(['current_organization_id' => $nextOrganizationId ?: null]);
        }

        return redirect()->back()->with('success', 'Organization member updated successfully.');
    }

    public function removeMember(Organization $organization, User $user)
    {
        $exists = $organization->users()->where('users.id', $user->id)->exists();
        if (! $exists) {
            abort(404, 'Member not found in organization.');
        }

        $membersCount = $organization->users()->count();
        if ($organization->slug === 'roni-plus' && $membersCount <= 1) {
            return redirect()->back()->with('error', 'Roni Plus must have at least one member.');
        }

        $organization->users()->detach($user->id);

        if ($user->current_organization_id === $organization->id) {
            $nextOrganizationId = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('organization_id')
                ->value('organization_id');

            $user->update(['current_organization_id' => $nextOrganizationId ?: null]);
        }

        return redirect()->back()->with('success', 'Organization member removed successfully.');
    }
}
