<?php

namespace App\Support;

use App\CompetitorConfig;

/**
 * Исключение агрегаторов и маркетплейсов из сравнения SERP (геозависимость и др.).
 */
class CompetitorSerpDomainFilter
{
    /** @var array<int, string>|null */
    protected static $excludedCache;

    /**
     * @return array<int, string> нормализованные хосты (без www)
     */
    public static function excludedDomains(): array
    {
        if (static::$excludedCache !== null) {
            return static::$excludedCache;
        }

        $domains = config('cabinet-competitor-analysis.geo_exclude_domains_default', []);
        if (! is_array($domains)) {
            $domains = [];
        }

        $config = CompetitorConfig::first();
        if ($config !== null && is_string($config->agrigators) && $config->agrigators !== '') {
            $domains = array_merge($domains, static::parseDomainLines($config->agrigators));
        }

        $normalized = [];
        foreach ($domains as $domain) {
            $host = static::normalizeHost((string) $domain);
            if ($host !== '') {
                $normalized[$host] = $host;
            }
        }

        static::$excludedCache = array_values($normalized);

        return static::$excludedCache;
    }

    public static function clearCache(): void
    {
        static::$excludedCache = null;
    }

    public static function isExcludedUrl(string $url): bool
    {
        $normalized = static::normalizeSerpUrl($url);

        return $normalized !== '' && static::isExcludedNormalized($normalized);
    }

    public static function isExcludedNormalized(string $normalized): bool
    {
        $host = static::hostFromNormalized($normalized);

        return $host !== '' && static::hostMatchesExcludedList($host, static::excludedDomains());
    }

    public static function hostFromNormalized(string $normalized): string
    {
        $slash = strpos($normalized, '/');

        return $slash === false ? $normalized : substr($normalized, 0, $slash);
    }

    public static function hostMatchesExcludedList(string $host, array $domainList): bool
    {
        $h = static::normalizeHost($host);
        if ($h === '' || count($domainList) === 0) {
            return false;
        }

        foreach ($domainList as $entry) {
            $entry = static::normalizeHost((string) $entry);
            if ($entry === '') {
                continue;
            }
            if ($h === $entry || substr($h, -strlen('.' . $entry)) === '.' . $entry) {
                return true;
            }
            if (strpos($entry, '.') !== false && strpos($h, $entry) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/^https?:\/\//i', '', $host);
        if ($host === null) {
            return '';
        }
        $host = explode('/', $host, 2)[0];

        return preg_replace('/^www\./', '', $host) ?? '';
    }

    public static function normalizeSerpUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = static::normalizeHost($parts['host']);
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $host . $path;
    }

    /**
     * @return array<int, string>
     */
    protected static function parseDomainLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (! is_array($lines)) {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            $host = static::normalizeHost($line);
            if ($host !== '') {
                $out[] = $host;
            }
        }

        return $out;
    }
}
