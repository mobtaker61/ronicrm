<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignTemplate;
use App\Models\Customer;
use App\Models\Industry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(): Response
    {
        $campaigns = Campaign::with(['creator', 'recipients'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Campaigns/Create', [
            'templates' => CampaignTemplate::orderBy('name')->get()->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'content' => $template->content,
                    'subject' => $template->subject,
                    'image' => $template->image ? asset('storage/' . $template->image) : null,
                    'type' => $template->type,
                ];
            }),
            'industries' => Industry::with('parent', 'children')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'customers' => Customer::with(['contacts', 'industry'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:whatsapp,email',
            'template_id' => 'nullable|exists:campaign_templates,id',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'scheduled_at' => 'nullable|date',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'exists:customers,id',
            'filters' => 'nullable|array',
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('campaign-images', 'public');
        }

        $campaign = Campaign::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['scheduled_at'] ? 'scheduled' : 'draft',
            'scheduled_at' => $validated['scheduled_at'] ? now()->parse($validated['scheduled_at']) : null,
            'subject' => $validated['subject'] ?? null,
            'content' => $validated['content'],
            'image' => $validated['image'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Create recipients
        foreach ($validated['recipient_ids'] as $customerId) {
            $campaign->recipients()->create([
                'customer_id' => $customerId,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign created successfully.');
    }

    public function show(Campaign $campaign): Response
    {
        $campaign->load([
            'recipients.customer.contacts',
            'recipients.customer.industry',
            'creator',
            'logs'
        ]);

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaign,
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        if ($campaign->status === 'running') {
            return redirect()->back()
                ->with('error', 'Cannot delete a running campaign.');
        }

        $campaign->delete();

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }
}
