<?php

namespace App\Services;

use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class I18nService
{
    public function getMap(string $locale): array
    {
        $locale = trim($locale);
        if ($locale === '') {
            return [];
        }

        $cacheKey = "i18n_map_{$locale}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($locale) {
            $fromFile = $this->readFromFile($locale);
            if ($fromFile !== null) {
                return $fromFile;
            }

            return $this->buildFromDb($locale);
        });
    }

    public function translate(string $key, ?string $fallback = null, ?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $map = $this->getMap($locale);

        if (array_key_exists($key, $map)) {
            return (string) $map[$key];
        }

        return $fallback ?? $key;
    }

    protected function readFromFile(string $locale): ?array
    {
        $path = storage_path('app/i18n/'.$locale.'.json');
        if (! File::exists($path)) {
            return null;
        }
        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function buildFromDb(string $locale): array
    {
        $lang = \App\Models\Language::query()
            ->where('code', $locale)
            ->where('is_active', true)
            ->first(['id']);
        if (! $lang) {
            return [];
        }

        $keys = TranslationKey::query()->get(['id', 'namespace', 'key']);
        if ($keys->isEmpty()) {
            return [];
        }

        $values = \App\Models\TranslationValue::query()
            ->where('language_id', $lang->id)
            ->whereIn('translation_key_id', $keys->pluck('id'))
            ->get(['translation_key_id', 'value'])
            ->keyBy('translation_key_id');

        $map = [];
        foreach ($keys as $k) {
            $val = $values->get($k->id)?->value;
            if ($val === null || trim((string) $val) === '') {
                continue;
            }
            $map[$k->namespace.'.'.$k->key] = $val;
        }
        return $map;
    }
}

