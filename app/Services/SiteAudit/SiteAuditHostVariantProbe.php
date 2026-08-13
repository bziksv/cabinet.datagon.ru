<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use GuzzleHttp\Client;
use GuzzleHttp\RedirectMiddleware;

/**
 * Проверка зеркал хоста: www и без www (и опционально http/https).
 * Если оба варианта отдают контент без свода на один хост — critical.
 */
class SiteAuditHostVariantProbe
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client([
            'timeout' => 12,
            'connect_timeout' => 6,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => 8,
                'track_redirects' => true,
            ],
            'verify' => true,
            'headers' => [
                'User-Agent' => (string) (config('site_audit.user_agents.0')
                    ?: config('site_audit.user_agent', 'Mozilla/5.0')),
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ],
        ]);
    }

    public function run(SiteAuditCrawl $crawl, string $domain): void
    {
        $bare = preg_replace('/^www\./i', '', strtolower(trim($domain)));
        $bare = preg_replace('#^https?://#i', '', $bare);
        $bare = rtrim((string) $bare, '/');
        if ($bare === '') {
            return;
        }

        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', ['www_both_available', 'http_https_both_available'])
            ->delete();

        $httpsApex = 'https://' . $bare . '/';
        $httpsWww = 'https://www.' . $bare . '/';
        $httpApex = 'http://' . $bare . '/';

        $apex = $this->probe($httpsApex);
        $www = $this->probe($httpsWww);

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['host_variants'] = [
            'apex' => $apex,
            'www' => $www,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        if ($this->bothLiveOnDifferentHosts($apex, $www)) {
            $this->createFinding($crawl->id, 'www_both_available', $httpsApex, $this->wwwFindingMeta(
                $bare,
                $httpsApex,
                $httpsWww,
                $apex,
                $www
            ));
        }

        // http открывается «сам по себе», без редиректа на https того же хоста
        $http = $this->probe($httpApex);
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['host_variants']['http'] = $http;
        $crawl->progress_json = $progress;
        $crawl->save();

        if ($this->httpServesWithoutHttpsCanonical($http, $apex, $www)) {
            // isLive() ждёт результат probe(), не URL-строку (TypeError валил весь host_variants).
            $httpsTarget = $this->isLive($www) && $this->hasWww((string) ($www['final_host'] ?? ''))
                ? $httpsWww
                : $httpsApex;
            $this->createFinding($crawl->id, 'http_https_both_available', $httpApex, [
                'http_url' => $httpApex,
                'http_status' => $http['status'],
                'http_final' => $http['final_url'],
                'http_final_scheme' => $http['final_scheme'],
                'http_redirected' => ! empty($http['redirected']),
                'http_redirect_statuses' => $http['redirect_statuses'] ?? [],
                'https_apex_url' => $httpsApex,
                'https_www_url' => $httpsWww,
                'https_apex_status' => $apex['status'],
                'https_www_status' => $www['status'],
                'https_target' => $httpsTarget,
                'fix_301_to' => $httpsTarget,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $apex
     * @param  array<string, mixed>  $www
     * @return array<string, mixed>
     */
    private function wwwFindingMeta(string $bare, string $httpsApex, string $httpsWww, array $apex, array $www): array
    {
        return [
            'bare_host' => $bare,
            'apex_url' => $httpsApex,
            'www_url' => $httpsWww,
            'apex_status' => $apex['status'],
            'www_status' => $www['status'],
            'apex_final' => $apex['final_url'],
            'www_final' => $www['final_url'],
            'apex_host' => $apex['final_host'],
            'www_host' => $www['final_host'],
            'apex_redirected' => ! empty($apex['redirected']),
            'www_redirected' => ! empty($www['redirected']),
            'apex_redirect_statuses' => $apex['redirect_statuses'] ?? [],
            'www_redirect_statuses' => $www['redirect_statuses'] ?? [],
            'apex_stays_on' => $this->hasWww((string) ($apex['final_host'] ?? '')) ? 'www' : 'apex',
            'www_stays_on' => $this->hasWww((string) ($www['final_host'] ?? '')) ? 'www' : 'apex',
            // Куда настраивать 301 (оба варианта — выбрать один канонический хост).
            'fix_301_to_apex' => $httpsApex,
            'fix_301_to_www' => $httpsWww,
        ];
    }

    /**
     * Оба варианта живы и финальные хосты различаются по www.
     *
     * @param array $a
     * @param array $b
     */
    private function bothLiveOnDifferentHosts(array $a, array $b): bool
    {
        if (! $this->isLive($a) || ! $this->isLive($b)) {
            return false;
        }

        $ha = $this->bareHost($a['final_host'] ?? '');
        $hb = $this->bareHost($b['final_host'] ?? '');
        if ($ha === '' || $hb === '' || $ha !== $hb) {
            // разные домены / сбой — не наш кейс
            return false;
        }

        $aWww = $this->hasWww($a['final_host'] ?? '');
        $bWww = $this->hasWww($b['final_host'] ?? '');

        // оба редиректят на один и тот же хост (оба www или оба без) — ок
        if ($aWww === $bWww) {
            return false;
        }

        // финалы на разных зеркалах www / non-www — грубое нарушение
        return true;
    }

    /**
     * @param array $http
     * @param array $httpsApex
     * @param array $httpsWww
     */
    private function httpServesWithoutHttpsCanonical(array $http, array $httpsApex, array $httpsWww): bool
    {
        if (! $this->isLive($http)) {
            return false;
        }
        // уже ушёл на https — норма
        if (($http['final_scheme'] ?? '') === 'https') {
            return false;
        }
        // https тоже жив — значит http отдаёт контент параллельно
        return $this->isLive($httpsApex) || $this->isLive($httpsWww);
    }

    /**
     * @param array $r
     */
    private function isLive(array $r): bool
    {
        if (empty($r['ok'])) {
            return false;
        }
        $code = (int) ($r['status'] ?? 0);

        return $code >= 200 && $code < 400;
    }

    private function hasWww(string $host): bool
    {
        return (bool) preg_match('/^www\./i', $host);
    }

    private function bareHost(string $host): string
    {
        return strtolower(preg_replace('/^www\./i', '', $host));
    }

    /**
     * @return array{
     *   ok:bool,
     *   status:?int,
     *   final_url:?string,
     *   final_host:?string,
     *   final_scheme:?string,
     *   redirected:bool,
     *   redirect_urls:list<string>,
     *   redirect_statuses:list<int>,
     *   error:?string
     * }
     */
    private function probe(string $url): array
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => ['Range' => 'bytes=0-0'],
            ]);
            $status = $response->getStatusCode();
            // Range иногда 206 — считаем успехом
            if ($status === 405 || $status === 501 || $status === 416) {
                $response = $this->client->request('GET', $url);
                $status = $response->getStatusCode();
            }

            $redirectUrls = [];
            $hist = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
            if (is_array($hist)) {
                foreach ($hist as $h) {
                    $h = trim((string) $h);
                    if ($h !== '') {
                        $redirectUrls[] = $h;
                    }
                }
            }
            $guzzleHist = $response->getHeader('X-Guzzle-Redirect-History');
            if ($redirectUrls === [] && is_array($guzzleHist)) {
                foreach ($guzzleHist as $h) {
                    $h = trim((string) $h);
                    if ($h !== '') {
                        $redirectUrls[] = $h;
                    }
                }
            }

            $redirectStatuses = [];
            $statusHist = $response->getHeader(RedirectMiddleware::STATUS_HISTORY_HEADER);
            if (! is_array($statusHist) || $statusHist === []) {
                $statusHist = $response->getHeader('X-Guzzle-Redirect-Status-History');
            }
            if (is_array($statusHist)) {
                foreach ($statusHist as $s) {
                    $code = (int) $s;
                    if ($code > 0) {
                        $redirectStatuses[] = $code;
                    }
                }
            }

            $final = $redirectUrls !== [] ? (string) end($redirectUrls) : $url;
            $host = SiteAuditUrlNormalizer::hostOf($final);
            $scheme = strtolower((string) (parse_url($final, PHP_URL_SCHEME) ?: 'https'));

            return [
                'ok' => true,
                'status' => $status,
                'final_url' => $final,
                'final_host' => $host,
                'final_scheme' => $scheme,
                'redirected' => $redirectUrls !== [] && strcasecmp(
                    rtrim($url, '/'),
                    rtrim($final, '/')
                ) !== 0,
                'redirect_urls' => array_values($redirectUrls),
                'redirect_statuses' => array_values($redirectStatuses),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'final_url' => null,
                'final_host' => null,
                'final_scheme' => null,
                'redirected' => false,
                'redirect_urls' => [],
                'redirect_statuses' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function createFinding(int $crawlId, string $code, string $url, array $meta): void
    {
        $cfg = config('site_audit.findings.' . $code, []);
        SiteAuditFinding::query()->create([
            'crawl_id' => $crawlId,
            'code' => $code,
            'severity' => $cfg['severity'] ?? 'critical',
            'url' => $url,
            'url_hash' => SiteAuditUrlNormalizer::hash($url),
            'meta_json' => $meta,
        ]);
    }
}
