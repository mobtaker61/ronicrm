<?php

namespace App\Services;

use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebScraperService
{
    /**
     * Fetch HTML from URL. Returns null on failure.
     */
    public function fetchHtml(string $url, int $timeout = 15): ?string
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('WebScraperService fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Extract data from HTML based on params.
     * Each param: name, selector_type (xpath|class|id), selector_value.
     * Returns associative array [ param_name => extracted_value ].
     */
    public function extract(string $html, array $params): array
    {
        $result = [];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        if (! $loaded) {
            return array_fill_keys(array_column($params, 'name'), '');
        }

        $xpath = new DOMXPath($dom);

        foreach ($params as $param) {
            $name = $param['name'] ?? '';
            $type = $param['selector_type'] ?? 'xpath';
            $value = trim($param['selector_value'] ?? '');
            if ($name === '' || $value === '') {
                $result[$name] = '';
                continue;
            }

            $expr = $this->selectorToXPath($type, $value);
            $nodes = $xpath->query($expr);
            $result[$name] = $this->extractNodeValue($nodes);
        }

        return $result;
    }

    /**
     * Extract a list of values from HTML: one selector matches many nodes, each node yields one value (text or attribute).
     * Config: selector_type, selector_value, value_kind (text|attribute), value_attr (e.g. href when value_kind=attribute).
     * When value looks like a URL (e.g. from href) and pageUrl is given, relative URLs are converted to absolute.
     * Returns array of strings.
     */
    public function extractList(string $html, array $config, ?string $pageUrl = null): array
    {
        $type = $config['selector_type'] ?? 'xpath';
        $selectorValue = trim($config['selector_value'] ?? '');
        $valueKind = $config['value_kind'] ?? 'text';
        $valueAttr = trim((string) ($config['value_attr'] ?? ''));
        if ($valueKind === 'attribute' && $valueAttr === '') {
            $valueAttr = 'href';
        }

        if ($selectorValue === '') {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $expr = $this->selectorToXPath($type, $selectorValue);
        $nodes = $xpath->query($expr);

        $firstHref = null;
        if ($nodes->length > 0 && $nodes->item(0) instanceof \DOMElement && $valueKind === 'attribute') {
            $firstHref = $nodes->item(0)->getAttribute($valueAttr);
        }
        Log::info('WebScraperService::extractList', [
            'nodes_count' => $nodes->length,
            'value_attr' => $valueAttr,
            'first_node_href' => $firstHref,
        ]);

        $list = [];
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            if ($valueKind === 'attribute' && $node instanceof \DOMElement) {
                $val = trim($node->getAttribute($valueAttr) ?? '');
                if ($val !== '' && $pageUrl !== null && $this->looksLikeRelativeUrl($val)) {
                    $val = $this->resolveRelativeUrl($val, $pageUrl);
                }
                $list[] = $val;
            } else {
                $texts = [];
                $this->collectText($node, $texts);
                $list[] = trim(implode(' ', $texts));
            }
        }

        return array_values(array_filter($list));
    }

    private function looksLikeRelativeUrl(string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) {
            return false;
        }

        return true;
    }

    private function resolveRelativeUrl(string $relative, string $pageUrl): string
    {
        $relative = trim($relative);
        if ($relative === '') {
            return $relative;
        }
        if (stripos($relative, 'http://') === 0 || stripos($relative, 'https://') === 0) {
            return $relative;
        }

        $scheme = parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($pageUrl, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return $relative;
        }

        $base = $scheme . '://' . $host;

        if (strpos($relative, '//') === 0) {
            return $scheme . ':' . $relative;
        }
        if (strpos($relative, '/') === 0) {
            return $base . $relative;
        }

        return $base . '/' . $relative;
    }

    /**
     * Extract list with pagination support. Handles delay and pagination (next_page or load_more).
     * Returns array of all extracted items from all pages.
     */
    public function extractListWithPagination(string $startUrl, array $config): array
    {
        $allItems = [];
        $currentUrl = $startUrl;
        $maxPages = $config['max_pages'] ?? 1;
        $delaySeconds = $config['delay_seconds'] ?? 0;
        $paginationType = $config['pagination_type'] ?? null;

        for ($page = 1; $page <= $maxPages; $page++) {
            // Fetch current page
            $html = $this->fetchHtml($currentUrl);
            if ($html === null) {
                Log::warning('WebScraperService::extractListWithPagination failed to fetch', ['url' => $currentUrl, 'page' => $page]);
                break;
            }

            // Apply delay if configured
            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            // Extract items from current page
            $pageItems = $this->extractList($html, $config, $currentUrl);
            $allItems = array_merge($allItems, $pageItems);

            Log::info('WebScraperService::extractListWithPagination page extracted', [
                'page' => $page,
                'items_count' => count($pageItems),
                'total_items' => count($allItems),
            ]);

            // If no pagination or last page, stop
            if (! $paginationType || $page >= $maxPages) {
                break;
            }

            // Find next page URL
            $nextUrl = $this->findNextPageUrl($html, $config, $currentUrl);
            if (! $nextUrl || $nextUrl === $currentUrl) {
                Log::info('WebScraperService::extractListWithPagination no more pages', ['current_url' => $currentUrl]);
                break;
            }

            $currentUrl = $nextUrl;
        }

        return array_values(array_filter($allItems));
    }

    /**
     * Find next page URL from HTML based on pagination config.
     */
    private function findNextPageUrl(string $html, array $config, string $currentUrl): ?string
    {
        $paginationType = $config['pagination_type'] ?? null;
        $selectorType = $config['pagination_selector_type'] ?? 'xpath';
        $selectorValue = trim($config['pagination_selector_value'] ?? '');

        if (! $paginationType || $selectorValue === '') {
            return null;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $expr = $this->selectorToXPath($selectorType, $selectorValue);
        $nodes = $xpath->query($expr);

        if ($nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);
        if (! $node instanceof \DOMElement) {
            return null;
        }

        // For next_page: usually an <a> tag with href
        if ($paginationType === 'next_page') {
            $href = $node->getAttribute('href');
            if ($href) {
                if ($this->looksLikeRelativeUrl($href)) {
                    return $this->resolveRelativeUrl($href, $currentUrl);
                }

                return $href;
            }
        }

        // For load_more: could be href, data-url, data-href, or onclick with URL
        if ($paginationType === 'load_more') {
            // Try href first
            $href = $node->getAttribute('href');
            if ($href && $href !== '#' && $href !== 'javascript:void(0)') {
                if ($this->looksLikeRelativeUrl($href)) {
                    return $this->resolveRelativeUrl($href, $currentUrl);
                }

                return $href;
            }

            // Try data-url
            $dataUrl = $node->getAttribute('data-url');
            if ($dataUrl) {
                if ($this->looksLikeRelativeUrl($dataUrl)) {
                    return $this->resolveRelativeUrl($dataUrl, $currentUrl);
                }

                return $dataUrl;
            }

            // Try data-href
            $dataHref = $node->getAttribute('data-href');
            if ($dataHref) {
                if ($this->looksLikeRelativeUrl($dataHref)) {
                    return $this->resolveRelativeUrl($dataHref, $currentUrl);
                }

                return $dataHref;
            }

            // Try onclick (extract URL from JavaScript)
            $onclick = $node->getAttribute('onclick');
            if ($onclick && preg_match('/["\']([^"\']+)["\']/', $onclick, $matches)) {
                $url = $matches[1];
                if ($this->looksLikeRelativeUrl($url)) {
                    return $this->resolveRelativeUrl($url, $currentUrl);
                }

                return $url;
            }
        }

        return null;
    }

    /**
     * Count how many nodes match the list selector (without extracting values). Useful for preview/progress.
     */
    public function countListMatches(string $html, array $config): int
    {
        $type = $config['selector_type'] ?? 'xpath';
        $selectorValue = trim($config['selector_value'] ?? '');
        if ($selectorValue === '') {
            return 0;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        if (! $loaded) {
            return 0;
        }

        $xpath = new DOMXPath($dom);
        $expr = $this->selectorToXPath($type, $selectorValue);
        $nodes = $xpath->query($expr);

        return $nodes->length;
    }

    private function selectorToXPath(string $type, string $value): string
    {
        $value = trim($value);
        if ($type === 'xpath') {
            return $value;
        }
        if ($type === 'id') {
            $escaped = addslashes($value);

            return "//*[@id='{$escaped}']";
        }
        if ($type === 'class') {
            $escaped = addslashes($value);
            // Match element that has this class (supports multiple classes)
            return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$escaped} ')]";
        }

        return $value;
    }

    private function extractNodeValue(DOMNodeList $nodes): string
    {
        if ($nodes->length === 0) {
            return '';
        }
        $parts = [];
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $texts = [];
            if ($node instanceof \DOMElement) {
                if (strtolower($node->nodeName) === 'img') {
                    $src = $node->getAttribute('src');
                    if ($src !== '') {
                        $parts[] = $src;
                        continue;
                    }
                }
                if (strtolower($node->nodeName) === 'a') {
                    $href = $node->getAttribute('href');
                    if ($href !== '') {
                        $parts[] = $href;
                        continue;
                    }
                }
            }
            $this->collectText($node, $texts);
            $parts[] = trim(implode(' ', $texts));
        }

        return implode(' | ', array_filter($parts));
    }

    private function collectText(\DOMNode $node, array &$texts): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $t = trim($node->textContent);
            if ($t !== '') {
                $texts[] = $t;
            }

            return;
        }
        foreach ($node->childNodes as $child) {
            $this->collectText($child, $texts);
        }
    }
}
