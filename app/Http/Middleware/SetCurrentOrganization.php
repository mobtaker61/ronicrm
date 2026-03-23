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

        if (! $organizationId && $request->user()?->current_organization_id) {
            $organizationId = (int) $request->user()->current_organization_id;
        }

        if (! $organizationId) {
            $organizationId = Organization::query()->where('is_active', true)->orderBy('id')->value('id');
        }

        OrganizationContext::setOrganizationId($organizationId ? (int) $organizationId : null);

        return $next($request);
    }
}
