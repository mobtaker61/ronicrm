<?php

namespace App\Http\Controllers;

use App\Models\SocialMediaType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocialMediaTypeController extends Controller
{
    public function index(): Response
    {
        $socialMediaTypes = SocialMediaType::orderBy('sort_order')->get();

        return Inertia::render('Settings/SocialMediaTypes', [
            'socialMediaTypes' => $socialMediaTypes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'base_url' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        SocialMediaType::create($validated);

        return redirect()->back()
            ->with('success', 'Social media type created successfully.');
    }

    public function update(Request $request, SocialMediaType $socialMediaType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:255',
            'base_url' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $socialMediaType->update($validated);

        return redirect()->back()
            ->with('success', 'Social media type updated successfully.');
    }

    public function destroy(SocialMediaType $socialMediaType)
    {
        $socialMediaType->delete();

        return redirect()->back()
            ->with('success', 'Social media type deleted successfully.');
    }
}
