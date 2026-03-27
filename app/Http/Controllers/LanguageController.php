<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguageController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->isSuperAdmin()) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(): JsonResponse
    {
        $languages = Language::orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'sort_order', 'is_active', 'is_default']);
        return response()->json(['languages' => $languages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'direction' => 'nullable|string|in:ltr,rtl',
            'font_family' => 'nullable|string|max:120',
        ]);
        $validated['sort_order'] = $validated['sort_order'] ?? Language::max('sort_order') + 1;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['is_default'] = (bool) ($validated['is_default'] ?? false);
        $validated['direction'] = $validated['direction'] ?? 'ltr';
        $validated['font_family'] = $validated['font_family'] ?? null;
        if ($validated['is_default']) {
            Language::query()->update(['is_default' => false]);
        }
        $lang = Language::create($validated);
        return response()->json(['success' => true, 'language' => $lang]);
    }

    public function update(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code,' . $language->id,
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_default' => 'sometimes|boolean',
            'direction' => 'nullable|string|in:ltr,rtl',
            'font_family' => 'nullable|string|max:120',
        ]);
        if (($validated['is_default'] ?? false) === true) {
            Language::query()->where('id', '!=', $language->id)->update(['is_default' => false]);
        }
        $validated['is_active'] = (bool) ($validated['is_active'] ?? $language->is_active);
        $validated['is_default'] = (bool) ($validated['is_default'] ?? $language->is_default);
        $validated['direction'] = $validated['direction'] ?? $language->direction;
        $validated['font_family'] = $validated['font_family'] ?? $language->font_family;
        $language->update($validated);
        return response()->json(['success' => true, 'language' => $language->fresh()]);
    }

    public function destroy(Language $language): JsonResponse
    {
        $language->delete();
        return response()->json(['success' => true]);
    }
}
