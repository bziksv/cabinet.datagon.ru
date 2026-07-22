<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Каннибализация рекламой (lite, без Direct API):
 * запрос из monitoring в title/h1 у promo/PPC-подобной страницы,
 * отличной от назначенной SEO-посадочной.
 *
 * Полный контур (Директ / объявления в SERP) — позже.
 */
class SiteAuditAdCannibalizationProbe
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
            ->get(['url', 'url_hash', 'title', 'h1', 'word_count']);

        if ($pages->count() < 2) {
            return;
        }

        $byHash = [];
        foreach ($pages as $page) {
            $byHash[$page->url_hash] = $page;
        }

        $max = (int) config('site_audit.ad_cannibalization_max', 200);
        $minTokenLen = max(3, (int) config('site_audit.cannibalization_min_token', 4));
        $minHits = max(1, (int) config('site_audit.cannibalization_min_hits', 2));
        $thinWords = max(20, (int) config('site_audit.ad_cannibalization_thin_words',
            (int) config('site_audit.thin_words', 150)));
        $cfg = config('site_audit.findings.ad_cannibalization', []);
        $severity = $cfg['severity'] ?? 'warning';
        $emitted = 0;
        $seen = [];

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

            $tokens = SiteAuditCannibalizationProbe::tokens($query, $minTokenLen);
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

                $adHint = self::adStyleHint($page, $thinWords);
                if ($adHint === null) {
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
                    'code' => 'ad_cannibalization',
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'query' => $query,
                        'monitoring_keyword_id' => (int) $kid,
                        'landing_url' => $landingUrl,
                        'hits' => $hits,
                        'full_match' => $fullMatch,
                        'ad_hint' => $adHint,
                        'competitor_title' => $page->title,
                        'word_count' => (int) ($page->word_count ?? 0),
                    ],
                ]);
                $emitted++;
            }
        }
    }

    /**
     * Эвристика promo/PPC-посадочной без данных Директа.
     *
     * @return string|null причина или null
     */
    public static function adStyleHint(SiteAuditPage $page, int $thinWords): ?string
    {
        $path = (string) (parse_url((string) $page->url, PHP_URL_PATH) ?: '/');
        $pathLower = mb_strtolower($path);

        if (preg_match(
            '#/(?:lp|landing|promo|offer|offers|sale|actions?|akci[iy]|cpc|ppc|adv|reklam|go|utm)(/|$)#u',
            $pathLower
        )) {
            return 'path_promo';
        }
        if (preg_match('#(?:^|/)(?:lp|promo|offer|cpc|ppc)[-_]#u', $pathLower)) {
            return 'path_promo_prefix';
        }

        $hay = mb_strtolower(trim((string) $page->title . ' ' . (string) $page->h1));
        $cta = 0;
        foreach (['заказать', 'купить', 'скидк', 'акци', 'заявк', 'оставить заявку', 'цена от', 'order now', 'buy now', 'sale'] as $w) {
            if ($hay !== '' && mb_strpos($hay, $w) !== false) {
                $cta++;
            }
        }

        $words = (int) ($page->word_count ?? 0);
        if ($words > 0 && $words <= $thinWords && $cta >= 1) {
            return 'thin_cta';
        }
        if ($cta >= 2 && $words > 0 && $words <= (int) ($thinWords * 1.5)) {
            return 'cta_heavy';
        }

        return null;
    }
}
