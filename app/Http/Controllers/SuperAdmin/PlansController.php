<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
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
        $plans = Plan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'billing_period' => $p->billing_period,
                'billing_interval' => $p->billing_interval,
                'price_cents' => $p->price_cents,
                'price_amount' => round(((int) $p->price_cents) / 100, 2),
                'currency' => $p->currency,
                'limits_json' => $p->limits_json,
                'is_active' => (bool) $p->is_active,
                'sort_order' => $p->sort_order,
            ]);

        return Inertia::render('SuperAdmin/Subscriptions/Plans', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'required|string|max:60|alpha_dash|unique:plans,code',
            'billing_period' => 'required|in:monthly,yearly,custom',
            'billing_interval' => 'required|integer|min:1|max:120',
            'price_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'limits_json' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['price_cents'] = (int) round(((float) $validated['price_amount']) * 100);
        unset($validated['price_amount']);

        Plan::create($validated);

        return redirect()->back()->with('success', 'پلن ایجاد شد.');
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'code' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('plans', 'code')->ignore($plan->id)],
            'billing_period' => 'required|in:monthly,yearly,custom',
            'billing_interval' => 'required|integer|min:1|max:120',
            'price_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'limits_json' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['price_cents'] = (int) round(((float) $validated['price_amount']) * 100);
        unset($validated['price_amount']);

        $plan->update($validated);

        return redirect()->back()->with('success', 'پلن به‌روزرسانی شد.');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return redirect()->back()->with('success', 'پلن حذف شد.');
    }
}

