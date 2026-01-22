<?php

namespace App\Http\Controllers;

use App\Models\Industry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndustryController extends Controller
{
    public function index(): Response
    {
        $industries = Industry::with('parent', 'children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $allIndustries = Industry::orderBy('name')->get();

        return Inertia::render('Industries/Index', [
            'industries' => $industries,
            'allIndustries' => $allIndustries,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|max:7',
            'parent_id' => 'nullable|exists:industries,id',
            'sort_order' => 'nullable|integer',
        ]);

        Industry::create($validated);

        return redirect()->route('industries.index')
            ->with('success', 'Industry created successfully.');
    }

    public function update(Request $request, Industry $industry)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|max:7',
            'parent_id' => 'nullable|exists:industries,id|different:' . $industry->id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Prevent circular reference
        if ($validated['parent_id'] && $industry->id == $validated['parent_id']) {
            return redirect()->route('industries.index')
                ->with('error', 'Cannot set industry as its own parent.');
        }

        // Check if parent is a descendant
        $parent = Industry::find($validated['parent_id']);
        if ($parent) {
            $descendants = $this->getDescendants($industry);
            if ($descendants->contains('id', $validated['parent_id'])) {
                return redirect()->route('industries.index')
                    ->with('error', 'Cannot set a descendant as parent.');
            }
        }

        $industry->update($validated);

        return redirect()->route('industries.index')
            ->with('success', 'Industry updated successfully.');
    }

    private function getDescendants(Industry $industry)
    {
        $descendants = collect();
        $industry->load('children');
        foreach ($industry->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getDescendants($child));
        }
        return $descendants;
    }

    public function destroy(Industry $industry)
    {
        // Check if industry has customers
        if ($industry->customers()->count() > 0) {
            return redirect()->route('industries.index')
                ->with('error', 'Cannot delete industry with existing customers.');
        }

        // Check if industry has children
        if ($industry->children()->count() > 0) {
            return redirect()->route('industries.index')
                ->with('error', 'Cannot delete industry with sub-industries. Please delete or move sub-industries first.');
        }

        $industry->delete();

        return redirect()->route('industries.index')
            ->with('success', 'Industry deleted successfully.');
    }
}
