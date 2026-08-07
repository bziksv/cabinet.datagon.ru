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

    /**
     * Откуда URL попал в краул, если HTML-referrer'ов нет.
     *
     * @param string[] $targetUrls
     * @return array<string, array{label:string,hint:string,from_sitemap:bool,from_seed:bool,from_home:bool,orphan:bool}>
     */
    public static function originMeta(SiteAuditCrawl $crawl, array $targetUrls): array
    {
        $out = [];
        foreach ($targetUrls as $u) {
            $u = trim((string) $u);
            if ($u === '') {
                continue;
            }
            $out[$u] = [
                'label' => '',
                'hint' => '',
                'from_sitemap' => false,
                'from_seed' => false,
                'from_home' => false,
                'orphan' => false,
            ];
        }
        if ($out === []) {
            return [];
        }

        $inSitemap = self::inSitemapFlags($crawl, array_keys($out));
        $project = $crawl->project;
        $domain = $project ? (string) $project->domain : '';
        $settings = is_array($crawl->progress_json['settings'] ?? null)
            ? $crawl->progress_json['settings']
            : [];
        $urlOpts = SiteAuditUrlNormalizer::optionsFromSettings($settings, $domain);

        $seedSet = [];
        if ($project) {
            $manual = $project->setting('seed_urls', []);
            if (is_array($manual)) {
                foreach ($manual as $su) {
                    $norm = SiteAuditUrlNormalizer::normalize((string) $su, $domain, $urlOpts);
                    if ($norm) {
                        $seedSet[$norm] = true;
                        $seedSet[(string) $su] = true;
                    }
                }
            }
        }

        $home = $domain !== ''
            ? (SiteAuditUrlNormalizer::normalize('https://' . $domain . '/', $domain, $urlOpts) ?: null)
            : null;
        $homeKey = $home ? SiteAuditUrlNormalizer::canonicalKey($home) : null;

        $pagesOnly = ! empty($crawl->progress_json['pages_only'])
            || ! empty($settings['pages_only']);

        $depths = SiteAuditPage::query()
            ->where('crawl_id', (int) $crawl->id)
            ->whereIn('url', array_keys($out))
            ->pluck('click_depth', 'url');

        $sitemapAvailable = SiteAuditSitemapProbe::urlsFromProgress($crawl) !== []
            || ! empty($crawl->progress_json['sitemap']['urls_gz_file'])
            || ! empty($crawl->progress_json['sitemap']['found']);

        foreach ($out as $url => $_) {
            $fromSitemap = ! empty($inSitemap[$url]);
            $norm = $domain !== ''
                ? (SiteAuditUrlNormalizer::normalize($url, $domain, $urlOpts) ?: $url)
                : $url;
            $fromSeed = isset($seedSet[$url]) || isset($seedSet[$norm]);
            $key = SiteAuditUrlNormalizer::canonicalKey($url);
            $fromHome = $home !== null && (
                $url === $home
                || $norm === $home
                || ($homeKey && $key && $key === $homeKey)
            );
            $depth = $depths[$url] ?? null;
            $orphan = $depth === null; // нет пути по HTML от главной

            $label = '';
            $hint = '';
            if ($fromSitemap) {
                $label = 'из sitemap.xml';
                $hint = 'URL взяли из карты сайта при старте обхода.';
            } elseif ($pagesOnly && $fromSeed) {
                $label = 'из списка URL';
                $hint = 'Режим «только страницы»: URL задали вручную в посеве.';
            } elseif ($fromSeed) {
                $label = 'из посева (список URL)';
                $hint = 'URL добавили вручную в seed при запуске.';
            } elseif ($fromHome) {
                $label = 'стартовый URL (главная)';
                $hint = 'Корень проекта — всегда в очереди на старте.';
            } elseif ($orphan && $sitemapAvailable) {
                // sitemap-файл мог уже стереться, но URL недостижим по ссылкам → почти наверняка sitemap/seed
                $label = 'из sitemap / посева';
                $hint = 'Внутренних HTML-ссылок с других страниц краула нет: URL попал в очередь при старте (обычно sitemap).';
            } elseif ($orphan) {
                $label = 'из стартовой очереди';
                $hint = 'Не из HTML-ссылок сохранённых страниц: посев, главная или sitemap при старте.';
            } else {
                $label = 'источник ссылки не сохранён';
                $hint = 'По графу глубины URL достижим, но исходящие ссылки со страниц-источников в крауле не записаны (часто если источник тоже 4xx/пусто).';
            }

            $out[$url] = [
                'label' => $label,
                'hint' => $hint,
                'from_sitemap' => $fromSitemap,
                'from_seed' => $fromSeed,
                'from_home' => $fromHome,
                'orphan' => $orphan,
            ];
        }

        return $out;
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

    /** Публичная обёртка для слэш-варианта (отчёт редиректов /about ↔ /about/). */
    public static function slashVariantPublic(string $url): ?string
    {
        return self::slashVariant($url);
    }

    /**
     * Коды отчётов, где нужна колонка «Откуда»: кто ссылается / sitemap / посев.
     *
     * @return string[]
     */
    public static function targetCodes(): array
    {
        return [
            'http_4xx',
            'http_5xx',
            'unreachable',
            'broken_internal_link',
            'redirect',
            'redirect_chain_long',
            'redirect_loop',
        ];
    }
}
