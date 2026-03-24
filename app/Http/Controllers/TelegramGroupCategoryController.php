<?php

namespace App\Http\Controllers;

use App\Models\TelegramGroupCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramGroupCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check() || ! Auth::user()->canManageOrganizationSettings()) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(): JsonResponse
    {
        $categories = TelegramGroupCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'sort_order']);
        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $validated['sort_order'] = $validated['sort_order'] ?? TelegramGroupCategory::max('sort_order') + 1;
        $cat = TelegramGroupCategory::create($validated);
        return response()->json(['success' => true, 'category' => $cat]);
    }

    public function update(Request $request, TelegramGroupCategory $telegramGroupCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $telegramGroupCategory->update($validated);
        return response()->json(['success' => true, 'category' => $telegramGroupCategory->fresh()]);
    }

    public function destroy(TelegramGroupCategory $telegramGroupCategory): JsonResponse
    {
        $telegramGroupCategory->groups()->update(['telegram_group_category_id' => null]);
        $telegramGroupCategory->delete();
        return response()->json(['success' => true]);
    }
}
