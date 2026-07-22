<?php

namespace App\Services\SiteAudit;

use App\Services\IndexCheckService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * PageSpeed Insights v5 (mobile/desktop) для сэмпла посадочных.
 * По умолчанию выкл.: SITE_AUDIT_PSI=1 (+ опционально SITE_AUDIT_PSI_API_KEY).
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

    public function run(SiteAuditCrawl $crawl): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', self::CODES)
            ->delete();

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $enabled = (bool) config('site_audit.psi_enabled', false);

        if (! $enabled) {
            $progress['psi'] = ['skipped' => true, 'reason' => 'disabled'];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $max = max(1, (int) config('site_audit.psi_max_urls', 3));
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

        $urls = $this->sampleUrls($crawl, $max);
        if ($urls === []) {
            $progress['psi'] = ['skipped' => true, 'reason' => 'no_urls'];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $warnBelow = (float) config('site_audit.psi_score_warn', 0.5);
        $apiKey = trim((string) config('site_audit.psi_api_key', ''));
        $rows = [];
        $errors = 0;

        foreach ($urls as $url) {
            $row = ['url' => $url, 'strategies' => []];
            foreach ($strategies as $strategy) {
                $result = $this->fetchPsi($url, $strategy, $apiKey);
                $row['strategies'][$strategy] = $result;

                if (! empty($result['error'])) {
                    $errors++;
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
                    'meta_json' => [
                        'strategy' => $strategy,
                        'score' => $score,
                        'score_pct' => $score !== null ? (int) round((float) $score * 100) : null,
                        'lcp_ms' => $result['lcp_ms'] ?? null,
                        'cls' => $result['cls'] ?? null,
                        'tbt_ms' => $result['tbt_ms'] ?? null,
                        'fcp_ms' => $result['fcp_ms'] ?? null,
                        'si_ms' => $result['si_ms'] ?? null,
                        'warn_below' => $warnBelow,
                        'psi_version' => $result['version'] ?? null,
                    ],
                ]);
            }
            $rows[] = $row;
        }

        $progress['psi'] = [
            'skipped' => false,
            'max_urls' => $max,
            'sampled' => count($urls),
            'strategies' => $strategies,
            'errors' => $errors,
            'warn_below' => $warnBelow,
            'rows' => $rows,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    /**
     * @return list<string>
     */
    private function sampleUrls(SiteAuditCrawl $crawl, int $max): array
    {
        $seen = [];
        $out = [];

        $add = function (string $url) use (&$seen, &$out, $max) {
            if (count($out) >= $max) {
                return;
            }
            $norm = IndexCheckService::normalizeUrl($url) ?: $url;
            $key = SiteAuditUrlNormalizer::hash($norm);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $out[] = $norm;
        };

        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        foreach ($resolved['urls'] as $url) {
            $add($url);
            if (count($out) >= $max) {
                return $out;
            }
        }

        if (optional($crawl->project)->domain) {
            $home = 'https://' . preg_replace('#^https?://#i', '', rtrim($crawl->project->domain, '/')) . '/';
            $add($home);
        }

        if (count($out) < $max) {
            $pages = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->whereNotNull('url')
                ->where(function ($q) {
                    $q->whereNull('status_code')->orWhereBetween('status_code', [200, 399]);
                })
                ->orderByRaw('CASE WHEN click_depth IS NULL THEN 999 ELSE click_depth END')
                ->orderBy('id')
                ->limit($max * 2)
                ->pluck('url');
            foreach ($pages as $u) {
                $add((string) $u);
                if (count($out) >= $max) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   score:?float,
     *   lcp_ms:?float,
     *   cls:?float,
     *   tbt_ms:?float,
     *   fcp_ms:?float,
     *   si_ms:?float,
     *   version:?string,
     *   error:?string
     * }
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
            'version' => null,
            'error' => null,
        ];

        $query = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
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
                $msg = is_array($json) ? (string) ($json['error']['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
                Log::warning('SiteAudit PSI HTTP error', [
                    'url' => $url,
                    'strategy' => $strategy,
                    'status' => $code,
                    'message' => $msg,
                ]);
                $empty['error'] = $msg;

                return $empty;
            }

            $lr = $json['lighthouseResult'] ?? [];
            $cats = $lr['categories'] ?? [];
            $audits = $lr['audits'] ?? [];
            $score = isset($cats['performance']['score']) ? (float) $cats['performance']['score'] : null;

            return [
                'score' => $score,
                'lcp_ms' => isset($audits['largest-contentful-paint']['numericValue'])
                    ? (float) $audits['largest-contentful-paint']['numericValue']
                    : null,
                'cls' => isset($audits['cumulative-layout-shift']['numericValue'])
                    ? (float) $audits['cumulative-layout-shift']['numericValue']
                    : null,
                'tbt_ms' => isset($audits['total-blocking-time']['numericValue'])
                    ? (float) $audits['total-blocking-time']['numericValue']
                    : null,
                'fcp_ms' => isset($audits['first-contentful-paint']['numericValue'])
                    ? (float) $audits['first-contentful-paint']['numericValue']
                    : null,
                'si_ms' => isset($audits['speed-index']['numericValue'])
                    ? (float) $audits['speed-index']['numericValue']
                    : null,
                'version' => isset($lr['lighthouseVersion']) ? (string) $lr['lighthouseVersion'] : null,
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
}
