<?php

namespace App\Http\Controllers;

use App\Models\CampaignTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = CampaignTemplate::orderBy('name')->get();

        return Inertia::render('Campaigns/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,email',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'variables' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('campaign-images', 'public');
        }

        CampaignTemplate::create($validated);

        return redirect()->route('campaign-templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function update(Request $request, CampaignTemplate $campaignTemplate)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:whatsapp,email',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'variables' => 'nullable|array',
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($campaignTemplate->image) {
                \Storage::disk('public')->delete($campaignTemplate->image);
            }
            $validated['image'] = $request->file('image')->store('campaign-images', 'public');
        }

        $campaignTemplate->update($validated);

        return redirect()->route('campaign-templates.index')
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(CampaignTemplate $campaignTemplate)
    {
        $campaignTemplate->delete();

        return redirect()->route('campaign-templates.index')
            ->with('success', 'Template deleted successfully.');
    }
}
