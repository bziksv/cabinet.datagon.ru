<?php

namespace App\Services\SiteAudit;

use App\Services\IndexCheckService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use Illuminate\Support\Facades\Log;

/**
 * Каннибализация по живым сниппетам: по запросу мониторинга в ТОП выдачи
 * ≥2 URL нашего домена → finding (не путать с duplicate_title на сайте).
 *
 * Gate: SITE_AUDIT_SERP_CANNIBALIZATION (по умолчанию = SITE_AUDIT_SERP_SNIPPETS).
 */
class SiteAuditSerpCannibalizationProbe
{
    public const CODE = 'serp_snippet_cannibalization';

    public function run(SiteAuditCrawl $crawl): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->where('code', self::CODE)
            ->delete();

        $enabled = (bool) config(
            'site_audit.serp_cannibalization_enabled',
            config('site_audit.serp_snippets_enabled', false)
        );
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];

        if (! $enabled) {
            $progress['serp_cannibalization'] = [
                'skipped' => true,
                'reason' => 'disabled',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $byKeyword = is_array($resolved['by_keyword'] ?? null) ? $resolved['by_keyword'] : [];
        if ($byKeyword === []) {
            $progress['serp_cannibalization'] = [
                'skipped' => true,
                'reason' => 'no_landings',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $domain = (string) optional($crawl->project)->domain;
        $allowedHosts = $this->allowedHosts($crawl, $domain);
        if ($allowedHosts === []) {
            $progress['serp_cannibalization'] = [
                'skipped' => true,
                'reason' => 'no_domain',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $maxQueries = max(1, (int) config('site_audit.serp_cannibalization_max_queries', 20));
        $depth = max(5, min(50, (int) config('site_audit.serp_cannibalization_depth', 20)));
        $engines = config('site_audit.serp_cannibalization_engines', ['yandex']);
        if (! is_array($engines) || $engines === []) {
            $engines = ['yandex'];
        }
        $engines = array_values(array_intersect(
            array_map('strtolower', $engines),
            ['yandex', 'google']
        ));
        if ($engines === []) {
            $engines = ['yandex'];
        }
        $lr = (string) config(
            'site_audit.serp_cannibalization_yandex_lr',
            config('cabinet-text-uniqueness.default_yandex_lr', '213')
        );
        $cfg = config('site_audit.findings.' . self::CODE, []);
        $severity = $cfg['severity'] ?? 'warning';

        // Уникальные запросы (первый keyword wins для landing).
        $queries = [];
        foreach ($byKeyword as $kid => $info) {
            $query = trim((string) ($info['query'] ?? ''));
            $landing = trim((string) ($info['url'] ?? ''));
            if (mb_strlen($query) < 3 || $landing === '') {
                continue;
            }
            $qKey = mb_strtolower($query);
            if (isset($queries[$qKey])) {
                continue;
            }
            $queries[$qKey] = [
                'query' => $query,
                'landing_url' => $landing,
                'monitoring_keyword_id' => (int) $kid,
            ];
            if (count($queries) >= $maxQueries) {
                break;
            }
        }

        $emitted = 0;
        $checked = 0;
        $errors = 0;
        $rows = [];

        foreach ($queries as $item) {
            $query = $item['query'];
            $landingUrl = $item['landing_url'];
            $landingHash = SiteAuditUrlNormalizer::hash($landingUrl);

            foreach ($engines as $engine) {
                $checked++;
                try {
                    $docs = IndexCheckService::searchQuery($query, $engine, $lr, $depth);
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('SiteAudit SERP cannibalization failed: ' . $e->getMessage(), [
                        'crawl_id' => $crawl->id,
                        'query' => $query,
                        'engine' => $engine,
                    ]);
                    continue;
                }

                $own = [];
                $seenHash = [];
                foreach ($docs as $doc) {
                    $url = (string) ($doc['url'] ?? '');
                    if ($url === '' || ! $this->urlOnAllowedHosts($url, $allowedHosts)) {
                        continue;
                    }
                    $norm = IndexCheckService::normalizeUrl($url) ?: $url;
                    $hash = SiteAuditUrlNormalizer::hash($norm);
                    if (isset($seenHash[$hash])) {
                        continue;
                    }
                    $seenHash[$hash] = true;
                    $own[] = [
                        'url' => $norm,
                        'url_hash' => $hash,
                        'position' => (int) ($doc['position'] ?? 0),
                        'title' => $doc['title'] ?? null,
                        'snippet' => $doc['snippet'] ?? null,
                        'is_landing' => $hash === $landingHash,
                    ];
                }

                if (count($own) < 2) {
                    continue;
                }

                $titlesSimilar = $this->titlesPairSimilar($own);
                $rows[] = [
                    'query' => $query,
                    'engine' => $engine,
                    'own_count' => count($own),
                    'titles_similar' => $titlesSimilar,
                    'hits' => $own,
                ];

                foreach ($own as $hit) {
                    if (! empty($hit['is_landing'])) {
                        continue;
                    }
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawl->id,
                        'code' => self::CODE,
                        'severity' => $severity,
                        'url' => $hit['url'],
                        'url_hash' => $hit['url_hash'],
                        'meta_json' => [
                            'query' => $query,
                            'engine' => $engine,
                            'monitoring_keyword_id' => $item['monitoring_keyword_id'],
                            'landing_url' => $landingUrl,
                            'position' => $hit['position'],
                            'serp_title' => $hit['title'],
                            'snippet' => $hit['snippet'],
                            'titles_similar' => $titlesSimilar,
                            'own_hits' => array_map(static function (array $h) {
                                return [
                                    'url' => $h['url'],
                                    'position' => $h['position'],
                                    'title' => $h['title'],
                                    'is_landing' => ! empty($h['is_landing']),
                                ];
                            }, $own),
                            'own_count' => count($own),
                        ],
                    ]);
                    $emitted++;
                }
            }
        }

        $progress['serp_cannibalization'] = [
            'skipped' => false,
            'queries' => count($queries),
            'checked' => $checked,
            'errors' => $errors,
            'emitted' => $emitted,
            'engines' => $engines,
            'depth' => $depth,
            'rows' => array_slice($rows, 0, 30),
        ];
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    /**
     * @return list<string> bare hosts lowercase without www
     */
    private function allowedHosts(SiteAuditCrawl $crawl, string $domain): array
    {
        $hosts = [];
        $add = static function (string $raw) use (&$hosts) {
            $raw = mb_strtolower(trim($raw));
            $raw = preg_replace('#^https?://#', '', $raw);
            $raw = explode('/', $raw)[0] ?? $raw;
            $raw = preg_replace('/:\d+$/', '', $raw);
            $raw = preg_replace('/^www\./', '', $raw);
            if ($raw !== '') {
                $hosts[$raw] = true;
            }
        };

        $add($domain);
        $settings = is_array($crawl->progress_json['settings'] ?? null)
            ? $crawl->progress_json['settings']
            : [];
        $extra = $settings['extra_hosts'] ?? [];
        if (is_array($extra)) {
            foreach ($extra as $h) {
                $add((string) $h);
            }
        }

        return array_keys($hosts);
    }

    /**
     * @param list<string> $allowedHosts
     */
    private function urlOnAllowedHosts(string $url, array $allowedHosts): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = mb_strtolower($host);
        $host = preg_replace('/^www\./', '', $host);

        return in_array($host, $allowedHosts, true);
    }

    /**
     * @param list<array{title:?string}> $own
     */
    private function titlesPairSimilar(array $own): bool
    {
        $titles = [];
        foreach ($own as $h) {
            $t = $this->normTitle((string) ($h['title'] ?? ''));
            if ($t !== '') {
                $titles[] = $t;
            }
        }
        $n = count($titles);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($titles[$i] === $titles[$j]) {
                    return true;
                }
                similar_text($titles[$i], $titles[$j], $pct);
                if ($pct >= 80) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normTitle(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = preg_replace('/\s+/u', ' ', $t);

        return $t !== null ? $t : '';
    }
}
