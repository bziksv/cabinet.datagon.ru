<?php

namespace App\Services\SiteAudit;

use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Обратный индекс: целевой URL → страницы краула, у которых он в out_links.
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
     * Коды отчётов, где URL = битая цель, а не страница-источник ссылки.
     *
     * @return string[]
     */
    public static function targetCodes(): array
    {
        return ['http_4xx', 'http_5xx', 'unreachable', 'broken_internal_link'];
    }
}
