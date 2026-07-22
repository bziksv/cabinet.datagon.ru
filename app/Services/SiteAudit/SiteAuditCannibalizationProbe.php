<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Каннибализация lite: запрос из monitoring встречается в title/h1
 * у страниц, отличных от назначенной посадочной.
 */
class SiteAuditCannibalizationProbe
{
    public function run(SiteAuditCrawl $crawl): void
    {
        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $byKeyword = is_array($resolved['by_keyword'] ?? null) ? $resolved['by_keyword'] : [];
        if ($byKeyword === []) {
            return;
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->get(['url', 'url_hash', 'title', 'h1']);

        if ($pages->count() < 2) {
            return;
        }

        $byHash = [];
        foreach ($pages as $page) {
            $byHash[$page->url_hash] = $page;
        }

        $max = (int) config('site_audit.cannibalization_max', 200);
        $minTokenLen = max(3, (int) config('site_audit.cannibalization_min_token', 4));
        $minHits = max(1, (int) config('site_audit.cannibalization_min_hits', 2));
        $cfg = config('site_audit.findings.keyword_cannibalization', []);
        $severity = $cfg['severity'] ?? 'warning';
        $emitted = 0;
        $seen = []; // landingHash|competitorHash|queryNorm

        foreach ($byKeyword as $kid => $info) {
            if ($emitted >= $max) {
                break;
            }
            $landingUrl = (string) ($info['url'] ?? '');
            $query = trim((string) ($info['query'] ?? ''));
            if ($landingUrl === '' || mb_strlen($query) < 4) {
                continue;
            }
            $landingHash = SiteAuditUrlNormalizer::hash($landingUrl);
            if (! isset($byHash[$landingHash])) {
                continue;
            }

            $tokens = self::tokens($query, $minTokenLen);
            if ($tokens === []) {
                continue;
            }
            $queryNorm = mb_strtolower($query);

            foreach ($pages as $page) {
                if ($emitted >= $max) {
                    break 2;
                }
                if ($page->url_hash === $landingHash) {
                    continue;
                }
                $hay = mb_strtolower(trim((string) $page->title . ' ' . (string) $page->h1));
                if ($hay === '') {
                    continue;
                }

                $hits = 0;
                foreach ($tokens as $tok) {
                    if (mb_strpos($hay, $tok) !== false) {
                        $hits++;
                    }
                }
                $fullMatch = mb_strpos($hay, $queryNorm) !== false;
                if (! $fullMatch && $hits < $minHits) {
                    continue;
                }

                $key = $landingHash . '|' . $page->url_hash . '|' . md5($queryNorm);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'keyword_cannibalization',
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'query' => $query,
                        'monitoring_keyword_id' => (int) $kid,
                        'landing_url' => $landingUrl,
                        'hits' => $hits,
                        'full_match' => $fullMatch,
                        'competitor_title' => $page->title,
                    ],
                ]);
                $emitted++;
            }
        }
    }

    /**
     * @return string[]
     */
    public static function tokens(string $query, int $minLen): array
    {
        $q = mb_strtolower($query);
        if (! preg_match_all('/[\p{L}\p{N}]{' . $minLen . ',}/u', $q, $m)) {
            return [];
        }
        $stop = [
            'для', 'или', 'как', 'это', 'the', 'and', 'with', 'from', 'http', 'https',
            'купить', 'цена', 'цены', // слишком общие коммерческие — оставляем в query full_match
        ];
        $out = [];
        foreach ($m[0] as $tok) {
            if (in_array($tok, $stop, true)) {
                continue;
            }
            $out[$tok] = true;
        }

        return array_keys($out);
    }
}
