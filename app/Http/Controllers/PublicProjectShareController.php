<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
                'allow_excel_export' => $project->allow_excel_export,
            ],
            'customers' => $customers,
        ]);
    }

    /**
     * Export project contacts as Excel-compatible CSV (only if project allows it).
     */
    public function exportExcel(string $shareToken): StreamedResponse
    {
        $project = Project::where('share_token', $shareToken)
            ->where('is_share_enabled', true)
            ->firstOrFail();

        if (!$project->allow_excel_export) {
            throw new NotFoundHttpException('Excel export is not enabled for this project.');
        }

        $customers = $project->customers()
            ->with(['industry', 'contacts', 'socialMedia.socialMediaType'])
            ->orderBy('name')
            ->get();

        $filename = 'project-' . Str::slug($project->name) . '-contacts-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($customers) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Name',
                'Company',
                'Industry',
                'Language',
                'Gender',
                'Contact Person',
                'Address',
                'Contact Methods',
                'Social Media & Links',
            ]);
            foreach ($customers as $c) {
                $contactMethods = $c->contacts->map(function ($contact) {
                    return ucfirst($contact->type) . ': ' . $contact->value;
                })->implode(' | ');
                $socialMedia = $c->socialMedia->map(function ($sm) {
                    $name = $sm->socialMediaType?->name ?? 'Social';
                    $url = $sm->url ?? $sm->handle;
                    return $name . ': ' . $url;
                })->implode(' | ');
                fputcsv($out, [
                    $c->name,
                    $c->company_name ?? '',
                    $c->industry?->name ?? '',
                    $c->language ?? '',
                    $c->gender ?? '',
                    $c->contact_person ?? '',
                    $c->address ?? '',
                    $contactMethods,
                    $socialMedia,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
