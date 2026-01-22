<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Campaign;
use App\Models\Industry;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_customers' => Customer::count(),
            'total_campaigns' => Campaign::count(),
            'active_campaigns' => Campaign::whereIn('status', ['scheduled', 'running'])->count(),
            'total_industries' => Industry::count(),
        ];

        // Customer distribution by status
        $customersByStatus = Customer::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Customer distribution by industry
        $customersByIndustry = Industry::withCount('customers')
            ->orderBy('customers_count', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($industry) => [
                'name' => $industry->name,
                'count' => $industry->customers_count,
            ]);

        // Recent campaigns
        $recentCampaigns = Campaign::with(['creator'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'customersByStatus' => $customersByStatus,
            'customersByIndustry' => $customersByIndustry,
            'recentCampaigns' => $recentCampaigns,
        ]);
    }
}
