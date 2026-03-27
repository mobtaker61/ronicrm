<?php

namespace App\Console\Commands;

use App\Models\Language;
use App\Models\TranslationKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class BuildTranslationsJson extends Command
{
    protected $signature = 'translations:build-json
        {--locale= : Build only one locale code (e.g. fa)}
        {--path= : Output directory (default: storage/app/i18n)}
        {--clear-cache : Clear cached maps after build}';

    protected $description = 'Build i18n JSON files from database translations';

    public function handle(): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/i18n'));
        $locale = trim((string) ($this->option('locale') ?: ''));

        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $languages = Language::query()
            ->when($locale !== '', fn ($q) => $q->where('code', $locale))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        if ($languages->isEmpty()) {
            $this->warn('No active languages found to build.');
            return self::SUCCESS;
        }

        $keys = TranslationKey::query()
            ->orderBy('namespace')
            ->orderBy('key')
            ->get(['id', 'namespace', 'key']);

        if ($keys->isEmpty()) {
            $this->warn('No translation keys found. JSON files will be empty.');
        }

        foreach ($languages as $lang) {
            $map = [];

            if ($keys->isNotEmpty()) {
                $values = \App\Models\TranslationValue::query()
                    ->where('language_id', $lang->id)
                    ->whereIn('translation_key_id', $keys->pluck('id'))
                    ->get(['translation_key_id', 'value'])
                    ->keyBy('translation_key_id');

                foreach ($keys as $k) {
                    $fullKey = $k->namespace . '.' . $k->key;
                    $val = $values->get($k->id)?->value;
                    if ($val === null || trim((string) $val) === '') {
                        continue;
                    }
                    $map[$fullKey] = $val;
                }
            }

            $json = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json === false) {
                $this->error('Failed to encode JSON for locale: ' . $lang->code);
                return self::FAILURE;
            }

            $outFile = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $lang->code . '.json';
            File::put($outFile, $json . PHP_EOL);

            $this->info("Built: {$outFile} (" . count($map) . ' entries)');
        }

        if ($this->option('clear-cache')) {
            try {
                Cache::tags(['i18n'])->flush();
                $this->info('Cleared i18n cache (tag: i18n).');
            } catch (\BadMethodCallException $e) {
                // File/Database cache drivers may not support tags.
                Cache::flush();
                $this->info('Cleared cache (full flush; cache tags not supported).');
            }
        }

        return self::SUCCESS;
    }
}

