<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicProjectShareController extends Controller
{
    public function show(string $shareToken): Response|JsonResponse
    {
        $project = Project::where('share_token', $shareToken)
            ->where('is_share_enabled', true)
            ->firstOrFail();

        $customers = $project->customers()
            ->with('industry')
            ->orderBy('name')
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'company_name' => $customer->company_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'share_key' => $customer->share_key,
                    'industry' => $customer->industry ? ['name' => $customer->industry->name] : null,
                ];
            });

        return Inertia::render('Public/ProjectShare', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
                'location' => $project->location,
                'share_token' => $project->share_token,
            ],
            'customers' => $customers,
        ]);
    }

    /**
     * Return full customer data for modal (only if customer belongs to this project).
     */
    public function getCustomer(string $shareToken, string $shareKey): JsonResponse
    {
        $project = Project::where('share_token', $shareToken)
            ->where('is_share_enabled', true)
            ->firstOrFail();

        $customer = Customer::where('share_key', $shareKey)
            ->where('project_id', $project->id)
            ->with(['industry', 'contacts', 'socialMedia.socialMediaType'])
            ->firstOrFail();

        return response()->json([
            'customer' => $customer,
        ]);
    }
}
