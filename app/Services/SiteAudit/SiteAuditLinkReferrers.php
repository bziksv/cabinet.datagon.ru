<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Обратный индекс: целевой URL → страницы краула, у которых он в out_links.
 * Плюс происхождение URL из sitemap (краул ходит не только по ссылкам).
 */
class SiteAuditLinkReferrers
{
    /**
     * @param string[]|null $targetUrls если задано — только эти цели
     * @return array<string, list<string>> targetUrl => [referrerUrl, ...]
     */
    public static function forCrawl(int $crawlId, ?array $targetUrls = null): array
    {
        $targets = null;
        if ($targetUrls !== null) {
            $targets = [];
            foreach ($targetUrls as $u) {
                $u = trim((string) $u);
                if ($u !== '') {
                    $targets[$u] = true;
                }
            }
            if ($targets === []) {
                return [];
            }
        }

        $map = [];

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull('out_links_json')
            ->get(['url', 'out_links_json']);

        foreach ($pages as $page) {
            $from = (string) $page->url;
            $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
            foreach ($outs as $out) {
                $out = (string) $out;
                if ($out === '' || (strlen($out) === 64 && ctype_xdigit($out))) {
                    continue;
                }
                if ($targets !== null && ! isset($targets[$out])) {
                    continue;
                }
                if (! isset($map[$out])) {
                    $map[$out] = [];
                }
                if (! in_array($from, $map[$out], true)) {
                    $map[$out][] = $from;
                }
            }
        }

        // Дополняем из findings «битая внутренняя ссылка» (meta.from).
        $q = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->where('code', 'broken_internal_link');
        if ($targets !== null) {
            $q->whereIn('url', array_keys($targets));
        }
        foreach ($q->get(['url', 'meta_json']) as $row) {
            $to = (string) $row->url;
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            $from = trim((string) ($meta['from'] ?? ''));
            if ($to === '' || $from === '') {
                continue;
            }
            if (! isset($map[$to])) {
                $map[$to] = [];
            }
            if (! in_array($from, $map[$to], true)) {
                $map[$to][] = $from;
            }
        }

        return $map;
    }

    /**
     * URL из sitemap текущего краула (с учётом trailing slash / canonical key).
     *
     * @param string[] $targetUrls
     * @return array<string, bool> targetUrl => true если есть в sitemap
     */
    public static function inSitemapFlags(SiteAuditCrawl $crawl, array $targetUrls): array
    {
        $flags = [];
        foreach ($targetUrls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $flags[$u] = false;
            }
        }
        if ($flags === []) {
            return [];
        }

        $sitemapUrls = SiteAuditSitemapProbe::urlsFromProgress($crawl);
        if ($sitemapUrls === []) {
            return $flags;
        }

        $byExact = [];
        $byKey = [];
        foreach ($sitemapUrls as $su) {
            $su = (string) $su;
            $byExact[$su] = true;
            $key = SiteAuditUrlNormalizer::canonicalKey($su);
            if ($key) {
                $byKey[$key] = true;
            }
            // sitemap часто со слэшем, краул — без (strip_trailing_slash)
            $alt = self::slashVariant($su);
            if ($alt !== null) {
                $byExact[$alt] = true;
            }
        }

        foreach ($flags as $url => $_) {
            if (isset($byExact[$url])) {
                $flags[$url] = true;
                continue;
            }
            $alt = self::slashVariant($url);
            if ($alt !== null && isset($byExact[$alt])) {
                $flags[$url] = true;
                continue;
            }
            $key = SiteAuditUrlNormalizer::canonicalKey($url);
            if ($key && isset($byKey[$key])) {
                $flags[$url] = true;
            }
        }

        return $flags;
    }

    private static function slashVariant(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        if ($path === '/' || $path === '') {
            return null;
        }
        if (substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        } else {
            $path .= '/';
        }
        $scheme = $parts['scheme'] ?? 'https';
        $out = $scheme . '://' . $parts['host'] . $path;
        if (! empty($parts['query'])) {
            $out .= '?' . $parts['query'];
        }

        return $out;
    }

    /**
     * Коды отчётов, где URL = битая цель, а не страница-источник ссылки.
     *
     * @return string[]
     */
    public static function targetCodes(): array
    {
        return ['http_4xx', 'http_5xx', 'unreachable', 'broken_internal_link'];
    }
}
