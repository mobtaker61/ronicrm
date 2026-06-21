<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = null;
        $slug = $request->route('organization');

        if (is_string($slug) && $slug !== '') {
            $organizationId = Organization::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->value('id');
        }

        $user = $request->user();

        if (! $organizationId && $user?->current_organization_id) {
            $organizationId = (int) $user->current_organization_id;
        }

        if (! $organizationId && $user) {
            $organizationId = $user->organizations()
                ->wherePivot('status', 'active')
                ->orderByDesc('organization_user.is_default')
                ->orderBy('organizations.id')
                ->value('organizations.id');
        }

        if (! $organizationId && ! $user) {
            $organizationId = Organization::query()->where('is_active', true)->orderBy('id')->value('id');
        }

        OrganizationContext::setOrganizationId($organizationId ? (int) $organizationId : null);

        return $next($request);
    }
}
