<?php

namespace App\Services;

use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class I18nService
{
    public function __construct(
        protected TranslationMapBuilder $mapBuilder
    ) {}

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
                $keyCount = TranslationKey::query()->count();
                if ($keyCount > 0 && count($fromFile) === $keyCount) {
                    return $fromFile;
                }
            }

            return $this->mapBuilder->buildMapForLocale($locale);
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
}

