<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OrganizationProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->canManageOrganizationSettings()) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function update(Request $request)
    {
        $orgId = Auth::user()->current_organization_id;
        if (! $orgId) {
            abort(404, 'No current organization.');
        }

        $organization = Organization::query()->findOrFail((int) $orgId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:2000'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:2'],
            'phone' => ['nullable', 'string', 'max:64'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $data = collect($validated)->except(['logo', 'remove_logo'])->all();

        if (! empty($validated['remove_logo'])) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($organization->logo_path) {
                Storage::disk('public')->delete($organization->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store("organizations/{$organization->id}", 'public');
        }

        $organization->update($data);

        return redirect()->route('settings.index', ['tab' => 'organization'])
            ->with('success', 'Organization profile updated.');
    }
}
