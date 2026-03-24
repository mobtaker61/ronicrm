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
        $languages = Language::orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'sort_order']);
        return response()->json(['languages' => $languages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $validated['sort_order'] = $validated['sort_order'] ?? Language::max('sort_order') + 1;
        $lang = Language::create($validated);
        return response()->json(['success' => true, 'language' => $lang]);
    }

    public function update(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code,' . $language->id,
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $language->update($validated);
        return response()->json(['success' => true, 'language' => $language->fresh()]);
    }

    public function destroy(Language $language): JsonResponse
    {
        $language->delete();
        return response()->json(['success' => true]);
    }
}
