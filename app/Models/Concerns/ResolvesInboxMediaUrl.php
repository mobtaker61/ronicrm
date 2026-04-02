<?php

namespace App\Models\Concerns;

/**
 * URL نمایش رسانه در اینباکس: لینک مطلق، uploads عمومی، یا storage.
 */
trait ResolvesInboxMediaUrl
{
    public function resolvedMediaUrl(): ?string
    {
        $url = $this->media_url;
        if (! $url) {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, 'uploads/')) {
            $abs = public_path($url);
            if (is_file($abs)) {
                return asset($url);
            }

            $dir = dirname($abs);
            $stem = pathinfo(basename($url), PATHINFO_FILENAME);
            if ($stem !== '' && is_dir($dir)) {
                foreach (glob($dir.DIRECTORY_SEPARATOR.$stem.'*') ?: [] as $found) {
                    if (! is_file($found)) {
                        continue;
                    }
                    $relative = substr($found, strlen(public_path()));
                    $relative = ltrim($relative, DIRECTORY_SEPARATOR.'/\\');
                    $relative = str_replace('\\', '/', $relative);
                    if ($relative !== '') {
                        return asset($relative);
                    }
                }
            }

            return asset($url);
        }

        return asset('storage/'.ltrim($url, '/'));
    }
}
