<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = Language::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->map(fn ($c) => (string) $c)
            ->values()
            ->all();

        $default = Language::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('code');

        $fallback = $default ?: (string) config('app.locale', 'en');

        $candidate = trim((string) $request->header('X-Locale', ''));
        if ($candidate === '') {
            $candidate = trim((string) $request->query('lang', ''));
        }
        if ($candidate === '') {
            $candidate = trim((string) $request->session()->get('locale', ''));
        }
        if ($candidate === '') {
            $candidate = $this->bestFromAcceptLanguage($request->header('Accept-Language', ''), $available);
        }

        $locale = in_array($candidate, $available, true) ? $candidate : $fallback;

        app()->setLocale($locale);
        $request->setLocale($locale);

        return $next($request);
    }

    protected function bestFromAcceptLanguage(?string $header, array $available): string
    {
        $header = trim((string) $header);
        if ($header === '' || $available === []) {
            return '';
        }

        $parts = array_map('trim', explode(',', $header));
        $candidates = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            $lang = strtolower(trim(explode(';', $p)[0] ?? ''));
            if ($lang !== '') {
                $candidates[] = $lang;
            }
        }

        foreach ($candidates as $c) {
            // exact
            if (in_array($c, $available, true)) return $c;
            // match prefix like en-US -> en
            $base = explode('-', $c)[0] ?? '';
            if ($base && in_array($base, $available, true)) return $base;
        }

        return '';
    }
}

