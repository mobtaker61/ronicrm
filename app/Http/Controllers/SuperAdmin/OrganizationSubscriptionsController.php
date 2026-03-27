<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSubscriptionsController extends Controller
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

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $orgs = Organization::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', '%'.$q.'%')->orWhere('slug', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'slug', 'is_active']);

        $plans = Plan::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code', 'price_cents', 'currency', 'billing_period', 'billing_interval', 'is_active']);

        $subs = OrganizationSubscription::query()
            ->whereIn('organization_id', $orgs->pluck('id'))
            ->with(['plan:id,name,code'])
            ->get()
            ->keyBy('organization_id');

        $service = app(SubscriptionService::class);

        $rows = $orgs->map(function ($o) use ($subs, $service) {
            $sub = $subs->get($o->id) ?: $service->getOrCreateForOrganization($o->id);
            return [
                'organization' => $o,
                'subscription' => [
                    'id' => $sub->id,
                    'plan_id' => $sub->plan_id,
                    'plan' => $sub->plan ? ['id' => $sub->plan->id, 'name' => $sub->plan->name, 'code' => $sub->plan->code] : null,
                    'status' => $service->computeStatus($sub),
                    'started_at' => $sub->started_at?->toDateString(),
                    'trial_ends_at' => $sub->trial_ends_at?->toDateString(),
                    'ends_at' => $sub->ends_at?->toDateString(),
                    'grace_ends_at' => $sub->grace_ends_at?->toDateString(),
                    'cancel_at_period_end' => (bool) $sub->cancel_at_period_end,
                ],
            ];
        })->values();

        return Inertia::render('SuperAdmin/Subscriptions/Organizations', [
            'filters' => ['q' => $q],
            'plans' => $plans,
            'rows' => $rows,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'plan_id' => 'nullable|integer|exists:plans,id',
            'started_at' => 'nullable|date',
            'trial_ends_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'grace_ends_at' => 'nullable|date',
            'cancel_at_period_end' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $sub = app(SubscriptionService::class)->getOrCreateForOrganization($organization->id);
        $sub->fill($validated);
        $sub->save();

        return redirect()->back()->with('success', 'اشتراک سازمان به‌روزرسانی شد.');
    }

    public function addPayment(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'amount_cents' => 'required|integer|min:0',
            'currency' => 'required|string|max:10',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string|max:190',
            'notes' => 'nullable|string',
        ]);

        $sub = app(SubscriptionService::class)->getOrCreateForOrganization($organization->id);

        SubscriptionPayment::create([
            'organization_id' => $organization->id,
            'organization_subscription_id' => $sub->id,
            'amount_cents' => $validated['amount_cents'],
            'currency' => $validated['currency'],
            'paid_at' => $validated['paid_at'] ?? now(),
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'پرداخت ثبت شد.');
    }
}

