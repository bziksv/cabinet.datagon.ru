<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * PageSpeed Insights v5 (mobile/desktop) для сэмпла посадочных.
 * По умолчанию вкл. при аудите (до psi_max_urls). Выкл.: SITE_AUDIT_PSI=0.
 * Опционально SITE_AUDIT_PSI_API_KEY для лимитов Google.
 *
 * Важно: меряет URL кусками по дедлайну тика агрегации — иначе 20×2 запросов
 * убивают AggregateSiteAuditCrawlJob по timeout и слот зависает.
 */
class SiteAuditPsiProbe
{
    private const CODES = ['psi_mobile', 'psi_desktop'];

    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $timeout = max(30, (int) config('site_audit.psi_timeout', 90));
        $this->client = $client ?: new Client([
            'timeout' => $timeout,
            'connect_timeout' => 15,
            'http_errors' => false,
            'verify' => true,
        ]);
    }

    /**
     * @return bool true — остались URL, нужен ещё тик агрегации
     */
    public function run(SiteAuditCrawl $crawl, bool $force = false, ?float $deadline = null): bool
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $block = is_array($progress['psi'] ?? null) ? $progress['psi'] : [];
        $enabled = $force || (bool) config('site_audit.psi_enabled', false);

        if (! $enabled) {
            $progress['psi'] = ['skipped' => true, 'reason' => 'disabled'];
            $crawl->progress_json = $progress;
            $crawl->save();

            return false;
        }

        // Уже домерили в прошлых тиках.
        if (! empty($block['done']) && empty($block['skipped'])) {
            return false;
        }

        $max = max(1, (int) config(
            'site_audit.psi_max_urls',
            config('site_audit.serp_snippets_max_urls', 30)
        ));
        $strategies = config('site_audit.psi_strategies', ['mobile', 'desktop']);
        if (! is_array($strategies) || $strategies === []) {
            $strategies = ['mobile', 'desktop'];
        }
        $strategies = array_values(array_intersect(
            array_map('strtolower', $strategies),
            ['mobile', 'desktop']
        ));
        if ($strategies === []) {
            $strategies = ['mobile'];
        }

        $urls = is_array($block['urls'] ?? null) ? $block['urls'] : null;
        $cursor = (int) ($block['cursor'] ?? 0);
        $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
        $errors = (int) ($block['errors'] ?? 0);
        $warnBelow = (float) ($block['warn_below'] ?? config('site_audit.psi_score_warn', 0.5));
        $apiKey = trim((string) config('site_audit.psi_api_key', ''));

        // Старт этапа: выборка URL + очистка старых findings один раз.
        if ($urls === null || ($cursor === 0 && $rows === [] && empty($block['started']))) {
            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', self::CODES)
                ->delete();

            $urls = $this->sampleUrls($crawl, $max);
            if ($urls === []) {
                $progress['psi'] = ['skipped' => true, 'reason' => 'no_urls'];
                $crawl->progress_json = $progress;
                $crawl->save();

                return false;
            }

            $cursor = 0;
            $rows = [];
            $errors = 0;
            $warnBelow = (float) config('site_audit.psi_score_warn', 0.5);
            Log::info('SiteAudit PSI start', [
                'crawl_id' => $crawl->id,
                'urls' => count($urls),
                'strategies' => $strategies,
            ]);
        }

        $urls = array_values(array_map('strval', $urls));
        $total = count($urls);
        // На тик — не больше 2 URL (до 4 HTTP); запас до дедлайна job.
        $perTick = max(1, (int) config('site_audit.psi_urls_per_tick', 2));
        $safety = 20.0;
        $processed = 0;

        while ($cursor < $total && $processed < $perTick) {
            if ($deadline !== null && microtime(true) >= ($deadline - $safety)) {
                break;
            }

            $url = $urls[$cursor];
            $row = ['url' => $url, 'strategies' => []];
            foreach ($strategies as $strategy) {
                if ($deadline !== null && microtime(true) >= ($deadline - $safety)) {
                    // не начинаем strategy, если уже на исходе
                    $progress['psi'] = [
                        'skipped' => false,
                        'started' => true,
                        'done' => false,
                        'max_urls' => $max,
                        'urls' => $urls,
                        'cursor' => $cursor,
                        'sampled' => $total,
                        'strategies' => $strategies,
                        'errors' => $errors,
                        'warn_below' => $warnBelow,
                        'rows' => $rows,
                    ];
                    $crawl->progress_json = $progress;
                    $crawl->save();
                    Log::info('SiteAudit PSI tick pause', [
                        'crawl_id' => $crawl->id,
                        'cursor' => $cursor,
                        'total' => $total,
                    ]);

                    return true;
                }

                $result = $this->fetchPsi($url, $strategy, $apiKey);
                $row['strategies'][$strategy] = [
                    'score' => $result['score'] ?? null,
                    'score_pct' => isset($result['score']) ? (int) round((float) $result['score'] * 100) : null,
                    'error' => $result['error'] ?? null,
                ];

                if (! empty($result['error'])) {
                    $errors++;
                    Log::warning('SiteAudit PSI HTTP error', [
                        'crawl_id' => $crawl->id,
                        'url' => $url,
                        'strategy' => $strategy,
                        'message' => $result['error'],
                    ]);
                    continue;
                }

                $score = $result['score'] ?? null;
                $code = $strategy === 'desktop' ? 'psi_desktop' : 'psi_mobile';
                $cfg = config('site_audit.findings.' . $code, []);
                $severity = $cfg['severity'] ?? 'info';
                if ($score !== null && (float) $score < $warnBelow) {
                    $severity = 'warning';
                }

                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => $code,
                    'severity' => $severity,
                    'url' => $url,
                    'url_hash' => SiteAuditUrlNormalizer::hash($url),
                    'meta_json' => $this->findingMeta($result, $strategy, $warnBelow),
                ]);
            }
            $rows[] = $row;
            $cursor++;
            $processed++;
        }

        $done = $cursor >= $total;
        $progress['psi'] = [
            'skipped' => false,
            'started' => true,
            'done' => $done,
            'max_urls' => $max,
            'urls' => $done ? [] : $urls, // не тащим весь список после финиша
            'cursor' => $cursor,
            'sampled' => $total,
            'strategies' => $strategies,
            'errors' => $errors,
            'warn_below' => $warnBelow,
            'rows' => $rows,
        ];

        if ($done && $errors > 0 && $rows !== []) {
            $ok = 0;
            foreach ($rows as $row) {
                foreach (($row['strategies'] ?? []) as $s) {
                    if (empty($s['error']) && isset($s['score'])) {
                        $ok++;
                    }
                }
            }
            if ($ok === 0) {
                $progress['psi']['skipped'] = true;
                $progress['psi']['reason'] = 'api_quota';
            }
        }

        $crawl->progress_json = $progress;
        $crawl->save();

        Log::info('SiteAudit PSI tick', [
            'crawl_id' => $crawl->id,
            'cursor' => $cursor,
            'total' => $total,
            'done' => $done,
            'errors' => $errors,
        ]);

        return ! $done;
    }

    /**
     * @return list<string>
     */
    private function sampleUrls(SiteAuditCrawl $crawl, int $max): array
    {
        // Тот же порядок URL, что у SERP XML-пакета (посадочные → краул).
        $rows = (new SiteAuditSerpUrlBatch())->sampleUrls($crawl, $max);
        $out = [];
        foreach ($rows as $row) {
            $url = (string) ($row['url'] ?? '');
            if ($url !== '') {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function findingMeta(array $result, string $strategy, float $warnBelow): array
    {
        $score = $result['score'] ?? null;

        return [
            'strategy' => $strategy,
            'score' => $score,
            'score_pct' => $score !== null ? (int) round((float) $score * 100) : null,
            'lcp_ms' => $result['lcp_ms'] ?? null,
            'cls' => $result['cls'] ?? null,
            'tbt_ms' => $result['tbt_ms'] ?? null,
            'fcp_ms' => $result['fcp_ms'] ?? null,
            'si_ms' => $result['si_ms'] ?? null,
            'tti_ms' => $result['tti_ms'] ?? null,
            'ttfb_ms' => $result['ttfb_ms'] ?? null,
            'inp_ms' => $result['inp_ms'] ?? null,
            'accessibility_pct' => $result['accessibility_pct'] ?? null,
            'best_practices_pct' => $result['best_practices_pct'] ?? null,
            'seo_pct' => $result['seo_pct'] ?? null,
            'opportunities' => $result['opportunities'] ?? [],
            'diagnostics' => $result['diagnostics'] ?? [],
            'field' => $result['field'] ?? null,
            'origin_field' => $result['origin_field'] ?? null,
            'fetch_time' => $result['fetch_time'] ?? null,
            'warn_below' => $warnBelow,
            'psi_version' => $result['version'] ?? null,
            'rich' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPsi(string $url, string $strategy, string $apiKey): array
    {
        $empty = [
            'score' => null,
            'lcp_ms' => null,
            'cls' => null,
            'tbt_ms' => null,
            'fcp_ms' => null,
            'si_ms' => null,
            'tti_ms' => null,
            'ttfb_ms' => null,
            'inp_ms' => null,
            'accessibility_pct' => null,
            'best_practices_pct' => null,
            'seo_pct' => null,
            'opportunities' => [],
            'diagnostics' => [],
            'field' => null,
            'origin_field' => null,
            'fetch_time' => null,
            'version' => null,
            'error' => null,
        ];

        $query = [
            'url' => $url,
            'strategy' => $strategy,
            // Все 4 категории Lighthouse — как в официальном PSI.
            'category' => ['performance', 'accessibility', 'best-practices', 'seo'],
        ];
        if ($apiKey !== '') {
            $query['key'] = $apiKey;
        }

        try {
            $response = $this->client->get(
                'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
                ['query' => $query]
            );
            $code = $response->getStatusCode();
            $body = (string) $response->getBody();
            $json = json_decode($body, true);

            if ($code < 200 || $code >= 300 || ! is_array($json)) {
                $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
                if ($msg === '') {
                    $msg = 'HTTP ' . $code;
                }
                $empty['error'] = $msg;

                return $empty;
            }

            $lh = is_array($json['lighthouseResult'] ?? null) ? $json['lighthouseResult'] : [];
            $cats = is_array($lh['categories'] ?? null) ? $lh['categories'] : [];
            $audits = is_array($lh['audits'] ?? null) ? $lh['audits'] : [];

            $catPct = static function (string $id) use ($cats): ?int {
                if (! isset($cats[$id]['score']) || $cats[$id]['score'] === null) {
                    return null;
                }

                return (int) round((float) $cats[$id]['score'] * 100);
            };

            $metric = static function (string $id) use ($audits): ?float {
                if (! isset($audits[$id]['numericValue'])) {
                    return null;
                }

                return (float) $audits[$id]['numericValue'];
            };

            return [
                'score' => isset($cats['performance']['score']) ? (float) $cats['performance']['score'] : null,
                'lcp_ms' => $metric('largest-contentful-paint'),
                'cls' => $metric('cumulative-layout-shift'),
                'tbt_ms' => $metric('total-blocking-time'),
                'fcp_ms' => $metric('first-contentful-paint'),
                'si_ms' => $metric('speed-index'),
                'tti_ms' => $metric('interactive'),
                'ttfb_ms' => $metric('server-response-time'),
                'inp_ms' => $metric('interaction-to-next-paint') ?? $metric('experimental-interaction-to-next-paint'),
                'accessibility_pct' => $catPct('accessibility'),
                'best_practices_pct' => $catPct('best-practices'),
                'seo_pct' => $catPct('seo'),
                'opportunities' => $this->extractOpportunities($audits, 8),
                'diagnostics' => $this->extractDiagnostics($audits, 6),
                'field' => $this->extractCrux($json['loadingExperience'] ?? null),
                'origin_field' => $this->extractCrux($json['originLoadingExperience'] ?? null),
                'fetch_time' => isset($lh['fetchTime']) ? (string) $lh['fetchTime'] : null,
                'version' => (string) ($lh['lighthouseVersion'] ?? ''),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('SiteAudit PSI exception: ' . $e->getMessage(), [
                'url' => $url,
                'strategy' => $strategy,
            ]);
            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * @param  array<string, mixed>  $audits
     * @return list<array{id:string,title:string,savings_ms:?int,savings_bytes:?int,display:?string}>
     */
    private function extractOpportunities(array $audits, int $limit): array
    {
        $items = [];
        foreach ($audits as $id => $audit) {
            if (! is_array($audit)) {
                continue;
            }
            $details = $audit['details'] ?? null;
            $isOpp = is_array($details) && (($details['type'] ?? '') === 'opportunity');
            if (! $isOpp) {
                continue;
            }
            $score = $audit['score'] ?? null;
            if ($score !== null && (float) $score >= 1) {
                continue;
            }
            $savingsMs = isset($details['overallSavingsMs'])
                ? (int) round((float) $details['overallSavingsMs'])
                : null;
            $savingsBytes = isset($details['overallSavingsBytes'])
                ? (int) round((float) $details['overallSavingsBytes'])
                : null;
            if ($savingsMs !== null && $savingsMs < 20 && ($savingsBytes === null || $savingsBytes < 1024)) {
                continue;
            }
            if ($savingsMs === null && $savingsBytes !== null && $savingsBytes < 1024) {
                continue;
            }
            $items[] = [
                'id' => (string) $id,
                'title' => (string) ($audit['title'] ?? $id),
                'savings_ms' => $savingsMs,
                'savings_bytes' => $savingsBytes,
                'display' => isset($audit['displayValue']) ? (string) $audit['displayValue'] : null,
            ];
        }
        usort($items, static function (array $a, array $b): int {
            return ($b['savings_ms'] ?? 0) <=> ($a['savings_ms'] ?? 0);
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $audits
     * @return list<array{id:string,title:string,display:?string}>
     */
    private function extractDiagnostics(array $audits, int $limit): array
    {
        $prefer = [
            'mainthread-work-breakdown',
            'bootup-time',
            'dom-size',
            'font-display',
            'third-party-summary',
            'long-tasks',
            'critical-request-chains',
            'uses-long-cache-ttl',
            'total-byte-weight',
            'network-rtt',
            'network-server-latency',
        ];
        $items = [];
        foreach ($prefer as $id) {
            if (! isset($audits[$id]) || ! is_array($audits[$id])) {
                continue;
            }
            $audit = $audits[$id];
            $score = $audit['score'] ?? null;
            if ($score !== null && (float) $score >= 0.9) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'title' => (string) ($audit['title'] ?? $id),
                'display' => isset($audit['displayValue']) ? (string) $audit['displayValue'] : null,
            ];
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * CrUX field data (реальные пользователи), если Google отдал.
     *
     * @param  mixed  $exp
     * @return array{overall:?string,metrics:array<string,array{percentile:?float,category:?string}>}|null
     */
    private function extractCrux($exp): ?array
    {
        if (! is_array($exp) || $exp === []) {
            return null;
        }
        $metrics = [];
        foreach ([
            'LARGEST_CONTENTFUL_PAINT_MS',
            'CUMULATIVE_LAYOUT_SHIFT_SCORE',
            'INTERACTION_TO_NEXT_PAINT',
            'FIRST_CONTENTFUL_PAINT_MS',
            'EXPERIMENTAL_TIME_TO_FIRST_BYTE',
        ] as $key) {
            if (! isset($exp['metrics'][$key]) || ! is_array($exp['metrics'][$key])) {
                continue;
            }
            $m = $exp['metrics'][$key];
            $metrics[$key] = [
                'percentile' => isset($m['percentile']) ? (float) $m['percentile'] : null,
                'category' => isset($m['category']) ? (string) $m['category'] : null,
            ];
        }
        if ($metrics === [] && empty($exp['overall_category'])) {
            return null;
        }

        return [
            'overall' => isset($exp['overall_category']) ? (string) $exp['overall_category'] : null,
            'metrics' => $metrics,
        ];
    }
}
