<?php

namespace App\Services\SiteAudit;

use App\SiteAuditPage;

/**
 * BFS-глубина клика от «корня» и путь URL до страницы.
 */
class SiteAuditClickDepth
{
    /**
     * @return array{
     *   depth_by_id: array<int,int>,
     *   path_by_url: array<string, list<string>>,
     *   max_depth: int
     * }
     */
    public static function compute(int $crawlId): array
    {
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->get(['id', 'url', 'url_hash', 'out_links_json']);

        if ($pages->isEmpty()) {
            return ['depth_by_id' => [], 'path_by_url' => [], 'max_depth' => 0];
        }

        $byUrl = [];
        $byHash = [];
        $byId = [];
        foreach ($pages as $page) {
            $byUrl[$page->url] = $page;
            $byHash[$page->url_hash] = $page;
            $byId[(int) $page->id] = $page;
        }

        $depth = [];
        $parent = [];
        $queue = [];
        foreach ($pages as $page) {
            $path = parse_url($page->url, PHP_URL_PATH);
            if ($path === '/' || $path === '' || $path === null) {
                $depth[(int) $page->id] = 0;
                $queue[] = $page;
            }
        }

        if ($queue === []) {
            $best = null;
            $bestLen = PHP_INT_MAX;
            foreach ($pages as $page) {
                $path = (string) (parse_url($page->url, PHP_URL_PATH) ?: '/');
                $len = strlen($path);
                if ($len < $bestLen) {
                    $bestLen = $len;
                    $best = $page;
                }
            }
            if ($best) {
                $depth[(int) $best->id] = 0;
                $queue[] = $best;
            }
        }

        $qi = 0;
        while ($qi < count($queue)) {
            $cur = $queue[$qi++];
            $curId = (int) $cur->id;
            $curDepth = $depth[$curId];
            $outs = is_array($cur->out_links_json) ? $cur->out_links_json : [];
            foreach ($outs as $out) {
                $out = (string) $out;
                $target = null;
                if (isset($byUrl[$out])) {
                    $target = $byUrl[$out];
                } elseif (isset($byHash[$out])) {
                    $target = $byHash[$out];
                } else {
                    $h = SiteAuditUrlNormalizer::hash($out);
                    if (isset($byHash[$h])) {
                        $target = $byHash[$h];
                    }
                }
                if (! $target) {
                    continue;
                }
                $tid = (int) $target->id;
                if (isset($depth[$tid])) {
                    continue;
                }
                $depth[$tid] = $curDepth + 1;
                $parent[$tid] = $curId;
                $queue[] = $target;
            }
        }

        $pathByUrl = [];
        $maxDepth = 0;
        foreach ($depth as $id => $d) {
            if ($d > $maxDepth) {
                $maxDepth = $d;
            }
            if (! isset($byId[$id])) {
                continue;
            }
            $chain = [];
            $pid = $id;
            $guard = 0;
            while ($pid && $guard++ < 64) {
                if (! isset($byId[$pid])) {
                    break;
                }
                array_unshift($chain, (string) $byId[$pid]->url);
                if (! isset($parent[$pid])) {
                    break;
                }
                $pid = $parent[$pid];
            }
            $pathByUrl[(string) $byId[$id]->url] = $chain;
        }

        return [
            'depth_by_id' => $depth,
            'path_by_url' => $pathByUrl,
            'max_depth' => $maxDepth,
        ];
    }
}
