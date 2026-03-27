<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Services\I18nService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class I18nController extends Controller
{
    public function json(Request $request, string $locale): JsonResponse
    {
        $locale = trim($locale);
        if ($locale === '') {
            return response()->json([], 404);
        }

        $isAllowed = Language::query()
            ->where('code', $locale)
            ->where('is_active', true)
            ->exists();

        if (! $isAllowed) {
            return response()->json([], 404);
        }

        $map = app(I18nService::class)->getMap($locale);

        return response()
            ->json($map)
            ->setPrivate()
            ->setMaxAge(300);
    }

    public function setLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:10',
        ]);

        $locale = trim((string) $validated['locale']);

        $isAllowed = Language::query()
            ->where('code', $locale)
            ->where('is_active', true)
            ->exists();

        if (! $isAllowed) {
            return response()->json(['success' => false, 'error' => 'invalid_locale'], 422);
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);
        $request->setLocale($locale);

        // Clear cached map so next request reflects file/DB changes faster.
        Cache::forget("i18n_map_{$locale}");

        return response()->json(['success' => true, 'locale' => $locale]);
    }
}

