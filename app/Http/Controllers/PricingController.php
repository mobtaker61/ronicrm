<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'billing_period' => $p->billing_period,
                'billing_interval' => $p->billing_interval,
                'price_cents' => $p->price_cents,
                'currency' => $p->currency,
                'limits_json' => $p->limits_json,
            ]);

        return Inertia::render('Pricing/Index', [
            'plans' => $plans,
            'isSuperAdmin' => Auth::user()?->isSuperAdmin() ?? false,
        ]);
    }
}

