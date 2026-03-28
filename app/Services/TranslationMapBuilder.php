<?php

namespace App\Services;

use App\Models\Language;
use App\Models\TranslationKey;
use App\Models\TranslationValue;

class TranslationMapBuilder
{
    /**
     * Flat map for i18n: "namespace.key" => string.
     * Includes every translation key; uses the locale value when non-empty, otherwise English.
     * Missing values become empty strings so the key exists and callers can distinguish from absent keys.
     */
    public function buildMapForLocale(string $localeCode): array
    {
        $localeCode = trim($localeCode);
        if ($localeCode === '') {
            return [];
        }

        $lang = Language::query()
            ->where('code', $localeCode)
            ->where('is_active', true)
            ->first(['id']);

        if (! $lang) {
            return [];
        }

        $enLang = Language::query()
            ->where('code', 'en')
            ->where('is_active', true)
            ->first(['id']);

        $keys = TranslationKey::query()
            ->orderBy('namespace')
            ->orderBy('key')
            ->get(['id', 'namespace', 'key']);

        if ($keys->isEmpty()) {
            return [];
        }

        $langIds = array_values(array_unique(array_filter([$lang->id, $enLang?->id])));

        $rows = TranslationValue::query()
            ->whereIn('language_id', $langIds)
            ->whereIn('translation_key_id', $keys->pluck('id'))
            ->get(['translation_key_id', 'language_id', 'value']);

        $byKeyId = [];
        foreach ($rows as $row) {
            $byKeyId[$row->translation_key_id][$row->language_id] = $row->value;
        }

        $map = [];
        foreach ($keys as $k) {
            $fullKey = $k->namespace.'.'.$k->key;
            $primary = trim((string) ($byKeyId[$k->id][$lang->id] ?? ''));
            if ($primary !== '') {
                $map[$fullKey] = $primary;

                continue;
            }
            if ($enLang && (int) $enLang->id !== (int) $lang->id) {
                $fallback = trim((string) ($byKeyId[$k->id][$enLang->id] ?? ''));
                if ($fallback !== '') {
                    $map[$fullKey] = $fallback;

                    continue;
                }
            }
            $map[$fullKey] = '';
        }

        return $map;
    }
}
