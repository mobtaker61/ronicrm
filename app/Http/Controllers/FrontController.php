<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FrontController extends Controller
{
    public function welcome(): Response|HttpResponse
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

        return Inertia::render('Front/Welcome', [
            'plans' => $plans,
        ]);
    }

    public function privacy(): Response
    {
        return Inertia::render('Front/PrivacyPolicy');
    }

    public function terms(): Response
    {
        return Inertia::render('Front/TermsAndConditions');
    }
}
