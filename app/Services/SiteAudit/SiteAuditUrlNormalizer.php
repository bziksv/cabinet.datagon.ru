<?php

namespace App\Services\SiteAudit;

class SiteAuditUrlNormalizer
{
    /**
     * @param array{force_https?:bool,prefer_host?:string,strip_trailing_slash?:bool} $opts
     */
    public static function normalize(string $url, ?string $baseHost = null, array $opts = []): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (! empty($opts['force_https'])) {
            $scheme = 'https';
        }

        $host = strtolower($parts['host']);
        $allowedBares = self::allowedBareHosts($baseHost, $opts);
        if ($allowedBares !== []) {
            $bare = preg_replace('/^www\./', '', $host);
            if (! in_array($bare, $allowedBares, true)) {
                return null;
            }
        }

        if (! empty($opts['prefer_host']) && empty($opts['allowed_hosts'])) {
            $host = strtolower((string) $opts['prefer_host']);
        } elseif (! empty($opts['strip_www'])) {
            $host = preg_replace('/^www\./', '', $host);
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        if (! empty($opts['strip_trailing_slash']) && $path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'yclid'] as $drop) {
                unset($query[$drop]);
            }
        }

        $out = $scheme . '://' . $host . $path;
        if ($query) {
            ksort($query);
            $out .= '?' . http_build_query($query);
        }

        return $out;
    }

    /**
     * Ключ для поиска дублей вариантов (www/http/slash/register).
     */
    public static function canonicalKey(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower($parts['host']));
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
            $path = strtolower($path);
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'yclid'] as $drop) {
                unset($query[$drop]);
            }
            ksort($query);
        }

        $key = $host . $path;
        if ($query) {
            $key .= '?' . http_build_query($query);
        }

        return $key;
    }

    public static function hash(string $url): string
    {
        return hash('sha256', $url);
    }

    public static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }

    public static function preferHostFromDomain(string $domain): string
    {
        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = rtrim((string) $domain, '/');

        return strtolower($domain);
    }

    /**
     * Опции нормализации из settings краула/проекта.
     *
     * @param array $settings
     * @return array
     */
    public static function optionsFromSettings(array $settings, string $domain): array
    {
        $prefer = self::preferHostFromDomain($domain);
        $extra = self::parseExtraHosts($settings['extra_hosts'] ?? []);
        $hasExtra = $extra !== [];

        $allowed = array_values(array_unique(array_merge(
            [preg_replace('/^www\./', '', strtolower($prefer))],
            $extra
        )));

        return [
            'force_https' => array_key_exists('force_https', $settings)
                ? (bool) $settings['force_https']
                : true,
            // prefer_host ломает доп. хосты — только без extra_hosts
            'prefer_host' => (! empty($settings['unify_www']) && ! $hasExtra) ? $prefer : null,
            'strip_www' => empty($settings['unify_www']) && ! empty($settings['strip_www']),
            'strip_trailing_slash' => array_key_exists('strip_trailing_slash', $settings)
                ? (bool) $settings['strip_trailing_slash']
                : true,
            'allowed_hosts' => $hasExtra ? $allowed : [],
        ];
    }

    /**
     * @param mixed $raw
     * @return string[] bare hosts
     */
    public static function parseExtraHosts($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $h) {
            $h = trim((string) $h);
            if ($h === '') {
                continue;
            }
            $h = preg_replace('#^https?://#i', '', $h);
            $h = rtrim((string) $h, '/');
            $host = parse_url('https://' . $h, PHP_URL_HOST) ?: $h;
            $bare = preg_replace('/^www\./', '', strtolower((string) $host));
            if ($bare !== '') {
                $out[$bare] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * @param array $opts
     * @return string[]
     */
    private static function allowedBareHosts(?string $baseHost, array $opts): array
    {
        // baseHost === null → «любой хост» (external assets/links); не режем allowed_hosts
        if ($baseHost === null || $baseHost === '') {
            return [];
        }

        $list = [preg_replace('/^www\./', '', strtolower($baseHost))];
        foreach ((array) ($opts['allowed_hosts'] ?? []) as $h) {
            $h = preg_replace('/^www\./', '', strtolower(trim((string) $h)));
            if ($h !== '') {
                $list[] = $h;
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Абсолютизация href/src относительно base URL; при $baseHost / allowed_hosts — только эти хосты.
     *
     * @param array $opts
     */
    public static function resolve(string $href, string $baseUrl, ?string $baseHost = null, array $opts = []): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $href)) {
            if (strpos($href, '//') === 0) {
                $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
                $href = $scheme . ':' . $href;
            }

            return self::normalize($href, $baseHost, $opts);
        }

        if (isset($href[0]) && $href[0] === '/') {
            $parts = parse_url($baseUrl);
            if ($parts === false || empty($parts['host'])) {
                return null;
            }
            $scheme = $parts['scheme'] ?? 'https';
            $abs = $scheme . '://' . $parts['host'] . $href;

            return self::normalize($abs, $baseHost, $opts);
        }

        $base = preg_replace('#/[^/]*$#', '/', $baseUrl);
        if (substr($base, -1) !== '/') {
            $base .= '/';
        }
        $abs = $base . $href;

        $parts = parse_url($abs);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $seg;
        }
        $path = '/' . implode('/', $segments);
        $rebuilt = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . $path;
        if (! empty($parts['query'])) {
            $rebuilt .= '?' . $parts['query'];
        }

        return self::normalize($rebuilt, $baseHost, $opts);
    }
}
