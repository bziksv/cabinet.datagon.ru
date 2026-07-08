<?php

namespace App\Services;

use App\Classes\Xml\SimplifiedXmlFacade;
use Illuminate\Support\Str;

class IndexCheckService
{
    /** @var array<int, string> */
    private const ROOT_INDEX_PATHS = [
        '/index.html',
        '/index.htm',
        '/index.php',
        '/default.aspx',
    ];

    /**
     * @param array{yandex?: bool, google?: bool, unify_www?: bool, google_lr?: string, yandex_lr?: string} $options
     * @return array<string, mixed>
     */
    public static function check(string $url, array $options = []): array
    {
        $normalized = self::normalizeUrl($url);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Некорректный URL');
        }

        $checkYandex = (bool) ($options['yandex'] ?? true);
        $checkGoogle = (bool) ($options['google'] ?? true);
        $unifyWww = (bool) ($options['unify_www'] ?? false);

        $result = [
            'url' => $normalized,
            'yandex' => null,
            'google' => null,
        ];

        if ($checkYandex) {
            $result['yandex'] = self::probeEngine($normalized, 'yandex', (string) ($options['yandex_lr'] ?? config('cabinet-index-check.default_yandex_lr', '213')), $unifyWww);
        }

        if ($checkGoogle) {
            $result['google'] = self::probeEngine($normalized, 'google', (string) ($options['google_lr'] ?? config('cabinet-index-check.default_google_lr', '213')), $unifyWww);
        }

        return $result;
    }

    /**
     * @return array{indexed: bool, results_count: int, matched_url: ?string, error: ?string}
     */
    private static function probeEngine(string $url, string $engine, string $lr, bool $unifyWww): array
    {
        $defaultDepth = max(10, (int) config('cabinet-index-check.serp_depth', 100));
        $queries = self::siteQueriesForUrl($url, $unifyWww);
        $urls = [];
        $hadError = false;

        foreach ($queries as $query) {
            $chunk = self::fetchSerpUrls($engine, $lr, $query, $defaultDepth, $hadError);
            if ($chunk !== []) {
                $urls = array_merge($urls, $chunk);
            }
        }

        $urls = array_values(array_unique($urls));
        $matched = self::findMatchingUrl($url, $urls, $unifyWww);

        if ($matched === null && self::isRootUrl($url)) {
            foreach (self::rootFallbackQueries($url) as $query) {
                $chunk = self::fetchSerpUrls($engine, $lr, $query, 10, $hadError);
                if ($chunk === []) {
                    continue;
                }
                $urls = array_values(array_unique(array_merge($urls, $chunk)));
                $matched = self::findMatchingUrl($url, $urls, $unifyWww);
                if ($matched !== null) {
                    break;
                }
            }
        }

        if ($urls === [] && $hadError) {
            return [
                'indexed' => false,
                'results_count' => 0,
                'matched_url' => null,
                'error' => 'Ошибка запроса к поисковой системе',
            ];
        }

        return [
            'indexed' => $matched !== null,
            'results_count' => count($urls),
            'matched_url' => $matched,
            'error' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function fetchSerpUrls(string $engine, string $lr, string $query, int $depth, bool &$hadError): array
    {
        try {
            $xml = new SimplifiedXmlFacade($lr, $depth);
            $xml->setQuery($query);
            $chunk = $xml->getXMLResponse($engine);

            return is_array($chunk) ? $chunk : [];
        } catch (\Throwable $e) {
            $hadError = true;

            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private static function siteQueriesForUrl(string $url, bool $unifyWww): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return ['site:' . $url];
        }

        $host = Str::lower((string) $parts['host']);
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        if (! self::isRootPath($path)) {
            return ['site:' . $host . $path];
        }

        $bare = preg_replace('/^www\./i', '', $host) ?? $host;
        $withWww = Str::startsWith($host, 'www.') ? $host : 'www.' . $bare;

        return array_values(array_unique([
            'site:' . $bare . '/',
            'site:' . $withWww . '/',
            'site:' . $bare,
            'site:' . $withWww,
        ]));
    }

    /**
     * Доп. запросы для главной: точный URL в выдаче (Яндекс часто не кладёт / в site:domain).
     *
     * @return array<int, string>
     */
    private static function rootFallbackQueries(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return [];
        }

        $bare = preg_replace('/^www\./i', '', Str::lower((string) $parts['host'])) ?? '';
        if ($bare === '') {
            return [];
        }

        $withWww = 'www.' . $bare;

        return array_values(array_unique([
            'https://' . $bare . '/',
            'https://' . $withWww . '/',
            'http://' . $bare . '/',
            'http://' . $withWww . '/',
            'https://' . $bare,
            'https://' . $withWww,
            $bare,
            $withWww,
        ]));
    }

    /**
     * @param array<int, string> $serpUrls
     */
    private static function findMatchingUrl(string $needle, array $serpUrls, bool $unifyWww): ?string
    {
        $variants = self::urlVariants($needle, $unifyWww);
        $needleIsRoot = self::isRootUrl($needle);

        foreach ($serpUrls as $serpUrl) {
            $candidate = self::normalizeComparableUrl((string) $serpUrl);
            if ($candidate === null) {
                continue;
            }

            foreach ($variants as $variant) {
                if ($candidate === $variant) {
                    return (string) $serpUrl;
                }
            }

            if ($needleIsRoot && self::isRootUrl((string) $serpUrl) && self::hostsEqual($needle, (string) $serpUrl, true)) {
                return (string) $serpUrl;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function urlVariants(string $url, bool $unifyWww): array
    {
        $base = self::normalizeComparableUrl($url);
        if ($base === null) {
            return [];
        }

        $variants = [$base];
        $withSlash = rtrim($base, '/') . '/';
        $withoutSlash = rtrim($base, '/');
        $variants[] = $withSlash;
        $variants[] = $withoutSlash;

        $expandSchemes = $unifyWww || self::isRootUrl($url);
        if ($expandSchemes) {
            foreach ([$base, $withSlash, $withoutSlash] as $variant) {
                $variants[] = self::toggleWww($variant);
                $variants[] = self::toggleScheme($variant);
                $variants[] = self::toggleScheme(self::toggleWww($variant));
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private static function toggleScheme(string $url): string
    {
        if (Str::startsWith($url, 'https://')) {
            return 'http://' . substr($url, 8);
        }
        if (Str::startsWith($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }

        return $url;
    }

    private static function toggleWww(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $host = (string) $parts['host'];
        if (Str::startsWith($host, 'www.')) {
            $host = substr($host, 4);
        } else {
            $host = 'www.' . $host;
        }

        $scheme = ($parts['scheme'] ?? 'https') . '://';
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . $host . $path . $query;
    }

    private static function isRootPath(string $path): bool
    {
        if ($path === '/' || $path === '') {
            return true;
        }

        $lower = Str::lower(rtrim($path, '/'));

        return in_array($lower, self::ROOT_INDEX_PATHS, true);
    }

    private static function isRootUrl(string $url): bool
    {
        $normalized = self::normalizeUrl($url);
        if ($normalized === null) {
            return false;
        }

        $parts = parse_url($normalized);
        if (! is_array($parts)) {
            return false;
        }

        return self::isRootPath($parts['path'] ?? '/');
    }

    private static function hostsEqual(string $urlA, string $urlB, bool $ignoreWww = false): bool
    {
        $a = parse_url(self::normalizeComparableUrl($urlA) ?? $urlA);
        $b = parse_url(self::normalizeComparableUrl($urlB) ?? $urlB);
        if (! is_array($a) || ! is_array($b) || empty($a['host']) || empty($b['host'])) {
            return false;
        }

        $hostA = (string) $a['host'];
        $hostB = (string) $b['host'];
        if ($ignoreWww) {
            $hostA = preg_replace('/^www\./i', '', $hostA) ?? $hostA;
            $hostB = preg_replace('/^www\./i', '', $hostB) ?? $hostB;
        }

        return $hostA === $hostB;
    }

    public static function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    private static function normalizeComparableUrl(string $url): ?string
    {
        $normalized = self::normalizeUrl($url);
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);
        if (! is_array($parts) || empty($parts['host'])) {
            return Str::lower($normalized);
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        if (self::isRootPath($path)) {
            $path = '/';
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? 'https'));
        $host = Str::lower((string) $parts['host']);
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    public static function costPerEngine(): int
    {
        return max(1, (int) config('cabinet-index-check.cost_per_engine', 1));
    }

    public static function checkCost(bool $yandex, bool $google): int
    {
        $cost = 0;
        if ($yandex) {
            $cost += self::costPerEngine();
        }
        if ($google) {
            $cost += self::costPerEngine();
        }

        return $cost;
    }
}
