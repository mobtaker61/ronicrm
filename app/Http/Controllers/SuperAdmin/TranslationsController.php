<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TranslationsController extends Controller
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

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $keys = TranslationKey::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where('key', 'like', '%'.$q.'%')
                    ->orWhere('namespace', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%');
            })
            ->orderBy('namespace')
            ->orderBy('key')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($k) => [
                'id' => $k->id,
                'namespace' => $k->namespace,
                'key' => $k->key,
                'full_key' => $k->namespace.'.'.$k->key,
                'description' => $k->description,
            ]);

        $languages = Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_default']);

        return Inertia::render('SuperAdmin/Translations/Index', [
            'filters' => [
                'q' => $q,
            ],
            'keys' => $keys,
            'languages' => $languages,
        ]);
    }

    public function storeKey(Request $request)
    {
        $validated = $request->validate([
            'namespace' => 'required|string|max:100',
            'key' => 'required|string|max:190',
            'description' => 'nullable|string|max:255',
        ]);

        $exists = TranslationKey::query()
            ->where('namespace', $validated['namespace'])
            ->where('key', $validated['key'])
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'این کلید قبلاً وجود دارد.');
        }

        TranslationKey::create($validated);

        return redirect()->back()->with('success', 'کلید ترجمه ایجاد شد.');
    }

    public function updateKey(Request $request, TranslationKey $translationKey)
    {
        $validated = $request->validate([
            'namespace' => 'required|string|max:100',
            'key' => 'required|string|max:190',
            'description' => 'nullable|string|max:255',
        ]);

        $request->validate([
            'key' => [
                Rule::unique('translation_keys', 'key')
                    ->where(fn ($q) => $q->where('namespace', $validated['namespace']))
                    ->ignore($translationKey->id),
            ],
        ]);

        $translationKey->update($validated);

        return redirect()->back()->with('success', 'کلید ترجمه به‌روزرسانی شد.');
    }

    public function destroyKey(TranslationKey $translationKey)
    {
        $translationKey->delete();

        return redirect()->back()->with('success', 'کلید ترجمه حذف شد.');
    }

    public function upsertValue(Request $request)
    {
        $validated = $request->validate([
            'translation_key_id' => 'required|integer|exists:translation_keys,id',
            'language_id' => 'required|integer|exists:languages,id',
            'value' => 'nullable|string',
        ]);

        TranslationValue::updateOrCreate(
            [
                'translation_key_id' => (int) $validated['translation_key_id'],
                'language_id' => (int) $validated['language_id'],
            ],
            [
                'value' => $validated['value'],
            ]
        );

        return response()->json(['success' => true]);
    }

    public function valuesForKey(TranslationKey $translationKey)
    {
        $vals = TranslationValue::query()
            ->where('translation_key_id', $translationKey->id)
            ->get(['language_id', 'value'])
            ->keyBy('language_id');

        return response()->json([
            'success' => true,
            'values' => $vals->map(fn ($v) => $v->value)->all(),
        ]);
    }

    public function buildJson()
    {
        Artisan::call('translations:build-json', ['--clear-cache' => true]);

        return redirect()->back()->with('success', 'فایل‌های JSON ترجمه ساخته/به‌روزرسانی شد.');
    }
}

