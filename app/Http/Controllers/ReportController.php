<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Industry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        // Customer Reports
        $customersByIndustry = Industry::withCount('customers')->get();
        $customersByStatus = Customer::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get();
        $customersBySource = Customer::selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->get();

        // Campaign Reports
        $campaignStats = [
            'total' => Campaign::count(),
            'completed' => Campaign::where('status', 'completed')->count(),
            'running' => Campaign::where('status', 'running')->count(),
            'scheduled' => Campaign::where('status', 'scheduled')->count(),
        ];

        $campaignsByType = Campaign::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get();

        $recentCampaigns = Campaign::with(['creator', 'recipients'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('Reports/Index', [
            'customersByIndustry' => $customersByIndustry,
            'customersByStatus' => $customersByStatus,
            'customersBySource' => $customersBySource,
            'campaignStats' => $campaignStats,
            'campaignsByType' => $campaignsByType,
            'recentCampaigns' => $recentCampaigns,
        ]);
    }
}
