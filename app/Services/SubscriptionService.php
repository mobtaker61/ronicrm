<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\OrganizationSubscription;
use Carbon\CarbonInterface;

class SubscriptionService
{
    public function getOrCreateForOrganization(int $organizationId): OrganizationSubscription
    {
        $existing = OrganizationSubscription::query()
            ->where('organization_id', $organizationId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return OrganizationSubscription::create([
            'organization_id' => $organizationId,
            'status' => 'trial',
            'started_at' => now(),
            'trial_ends_at' => now()->addDays(7),
        ]);
    }

    public function computeStatus(OrganizationSubscription $sub, ?CarbonInterface $at = null): string
    {
        $at = $at ?: now();

        if ($sub->status === 'canceled') {
            // If canceled but still within paid/trial period, keep computed window status.
        }

        if ($sub->trial_ends_at && $at->lessThanOrEqualTo($sub->trial_ends_at)) {
            return 'trial';
        }

        if ($sub->ends_at && $at->lessThanOrEqualTo($sub->ends_at)) {
            return 'active';
        }

        if ($sub->grace_ends_at && $at->lessThanOrEqualTo($sub->grace_ends_at)) {
            return 'grace';
        }

        return 'expired';
    }

    public function isActive(OrganizationSubscription $sub, ?CarbonInterface $at = null): bool
    {
        $status = $this->computeStatus($sub, $at);
        return in_array($status, ['trial', 'active', 'grace'], true);
    }

    public function remainingDays(OrganizationSubscription $sub, ?CarbonInterface $at = null): ?int
    {
        $at = $at ?: now();
        $status = $this->computeStatus($sub, $at);

        $until = match ($status) {
            'trial' => $sub->trial_ends_at,
            'active' => $sub->ends_at,
            'grace' => $sub->grace_ends_at,
            default => null,
        };

        if (! $until) {
            return null;
        }

        return max(0, (int) ceil($at->diffInHours($until, false) / 24));
    }
}

