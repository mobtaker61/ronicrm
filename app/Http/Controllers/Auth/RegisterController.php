<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\OwnerNewRegistrationNotification;
use App\Services\SubscriptionService;
use App\Support\PlatformNotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'alpha_dash:ascii', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole('user');

            $slug = $this->uniqueOrganizationSlug($validated['organization_name']);

            $organization = Organization::create([
                'name' => $validated['organization_name'],
                'slug' => $slug,
                'is_active' => true,
                'owner_user_id' => $user->id,
            ]);

            $organization->users()->attach($user->id, [
                'role_in_org' => 'org_admin',
                'status' => 'active',
                'is_default' => true,
            ]);

            $user->update(['current_organization_id' => $organization->id]);

            $defaultLangIds = Language::query()
                ->where('is_active', true)
                ->where('is_default', true)
                ->pluck('id')
                ->all();

            if ($defaultLangIds !== []) {
                $organization->languages()->sync($defaultLangIds);
            }

            $subscription = app(SubscriptionService::class)->getOrCreateForOrganization((int) $organization->id);

            if (! empty($validated['plan_id'])) {
                $plan = Plan::query()
                    ->where('id', (int) $validated['plan_id'])
                    ->where('is_active', true)
                    ->first();
                if ($plan) {
                    $subscription->update(['plan_id' => $plan->id]);
                }
            }

            return $user->fresh();
        });

        Auth::login($user);
        $request->session()->regenerate();

        $organization = Organization::query()->find($user->current_organization_id);
        if ($organization) {
            $settings = PlatformNotificationSettings::get();
            if ($settings['email_owner_new_registration']) {
                foreach (PlatformNotificationSettings::ownerEmails() as $ownerEmail) {
                    try {
                        Notification::route('mail', $ownerEmail)
                            ->notify(new OwnerNewRegistrationNotification($user, $organization));
                    } catch (\Throwable $e) {
                        Log::warning('Owner registration notification failed: '.$e->getMessage());
                    }
                }
            }
        }

        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice');
    }

    protected function uniqueOrganizationSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;
        $i = 0;
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
