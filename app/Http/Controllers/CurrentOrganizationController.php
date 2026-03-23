<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CurrentOrganizationController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $canAccess = $user->organizations()->where('organizations.id', $validated['organization_id'])->exists();
        if (! $canAccess && ! $user->hasRole('super_admin')) {
            abort(403, 'You do not have access to this organization.');
        }

        $user->update(['current_organization_id' => $validated['organization_id']]);

        return redirect()->back()->with('success', 'Current organization switched.');
    }
}
