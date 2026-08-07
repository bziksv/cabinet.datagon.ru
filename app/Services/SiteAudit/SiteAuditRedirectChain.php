<?php

namespace App\Services\SiteAudit;

/**
 * Цепочки редиректов: нормализация, детект циклов, формат для отчёта.
 */
class SiteAuditRedirectChain
{
    /**
     * @param string[] $chain URL из X-Guzzle-Redirect-History (хопы после старта)
     * @return array{loop:bool,at:?string,path:string[]}
     */
    public static function analyze(string $startUrl, array $chain, ?string $finalUrl = null): array
    {
        $path = [];
        $seen = [];
        $loopAt = null;

        $push = function (string $url) use (&$path, &$seen, &$loopAt) {
            $url = trim($url);
            if ($url === '') {
                return;
            }
            if ($path !== [] && end($path) === $url) {
                return;
            }
            $norm = self::normalize($url);
            if ($norm !== '' && isset($seen[$norm])) {
                $path[] = $url;
                if ($loopAt === null) {
                    $loopAt = $url;
                }

                return;
            }
            if ($norm !== '') {
                $seen[$norm] = true;
            }
            $path[] = $url;
        };

        $push($startUrl);
        foreach ($chain as $hop) {
            if (! is_string($hop) && ! is_numeric($hop)) {
                continue;
            }
            $push((string) $hop);
            if ($loopAt !== null) {
                break;
            }
        }
        if ($finalUrl && $loopAt === null) {
            $push($finalUrl);
        }

        return [
            'loop' => $loopAt !== null,
            'at' => $loopAt,
            'path' => $path,
        ];
    }

    /**
     * Полная цепочка для колонки «Детали».
     *
     * @param string[] $chain
     */
    public static function formatDetails(
        string $startUrl,
        array $chain = [],
        ?string $finalUrl = null,
        int $clipEach = 64,
        bool $markLoop = false
    ): string {
        $info = self::analyze($startUrl, $chain, $finalUrl);
        $parts = [];
        foreach ($info['path'] as $url) {
            $parts[] = self::clip($url, $clipEach);
        }
        if ($parts === []) {
            return '—';
        }
        $line = implode(' → ', $parts);
        if ($markLoop || $info['loop']) {
            $line .= ' · цикл';
        }
        $hops = max(0, count($info['path']) - 1);
        if ($hops > 0) {
            $line .= ' · шагов: ' . $hops;
        }

        return $line;
    }

    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return mb_strtolower($url);
        }
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? 'http'));
        $host = mb_strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (':' . (int) $parts['port']) : '';
        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) && $parts['query'] !== ''
            ? ('?' . $parts['query'])
            : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * Редирект только из‑за trailing slash: /about ↔ /about/ (тот же хост, путь, query).
     * Не путать с редиректом на другую страницу (/old → /new).
     */
    public static function isSlashOnlyRedirect(string $from, string $to): bool
    {
        $from = trim($from);
        $to = trim($to);
        if ($from === '' || $to === '' || self::normalize($from) === self::normalize($to)) {
            return false;
        }

        $a = parse_url($from);
        $b = parse_url($to);
        if (! is_array($a) || ! is_array($b) || empty($a['host']) || empty($b['host'])) {
            return false;
        }

        $schemeA = mb_strtolower((string) ($a['scheme'] ?? 'http'));
        $schemeB = mb_strtolower((string) ($b['scheme'] ?? 'http'));
        $hostA = mb_strtolower((string) $a['host']);
        $hostB = mb_strtolower((string) $b['host']);
        if ($schemeA !== $schemeB || $hostA !== $hostB) {
            return false;
        }
        if ((int) ($a['port'] ?? 0) !== (int) ($b['port'] ?? 0)) {
            return false;
        }
        if ((string) ($a['query'] ?? '') !== (string) ($b['query'] ?? '')) {
            return false;
        }

        $pathA = self::pathWithoutTrailingSlash((string) ($a['path'] ?? '/'));
        $pathB = self::pathWithoutTrailingSlash((string) ($b['path'] ?? '/'));

        return $pathA === $pathB;
    }

    /**
     * Старт → финал: смена страницы (не только слэш).
     *
     * @param string[] $path полная цепочка из analyze()['path']
     */
    public static function isOtherPageRedirect(string $startUrl, ?string $finalUrl, array $path = []): bool
    {
        $final = $finalUrl;
        if (($final === null || $final === '') && $path !== []) {
            $final = (string) end($path);
        }
        if ($final === null || $final === '') {
            return false;
        }

        return ! self::isSlashOnlyRedirect($startUrl, $final);
    }

    private static function pathWithoutTrailingSlash(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    private static function clip(string $text, int $len): string
    {
        $text = trim($text);
        if ($len < 8 || mb_strlen($text) <= $len) {
            return $text;
        }

        return mb_substr($text, 0, $len - 1) . '…';
    }
}
