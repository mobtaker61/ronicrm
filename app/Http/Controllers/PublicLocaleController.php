<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicLocaleController extends Controller
{
    /**
     * Set UI locale for guests (session). Authenticated users can use the same route for consistency.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:10',
        ]);

        $code = mb_strtolower(trim($validated['locale']));

        $allowed = Language::query()
            ->where('is_active', true)
            ->where('code', $code)
            ->exists();

        abort_unless($allowed, 422);

        $request->session()->put('locale', $code);
        Cache::forget('i18n_map_'.$code);

        return redirect()->back();
    }
}
