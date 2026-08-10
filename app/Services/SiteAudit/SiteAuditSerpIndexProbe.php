<?php

namespace App\Services\SiteAudit;

use App\SeoReports\SeoReportBindings;
use App\Services\YandexWebmaster\YandexWebmasterService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use App\SiteAuditProject;
use App\Support\HomeUserSites;

/**
 * Сверка индекса с краулом через Яндекс.Вебмастер.
 * Полный diff списка выполняется на этапе агрегации аудита (не отдельной кнопкой).
 */
class SiteAuditSerpIndexProbe
{
    public function run(SiteAuditCrawl $crawl, bool $force = false): void
    {
        $pagesTotal = (int) ($crawl->pages_total ?: 0);
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $prevDeep = is_array($progress['serp_index']['deep'] ?? null)
            ? $progress['serp_index']['deep']
            : null;

        $project = SiteAuditProject::query()->find($crawl->project_id);
        $domain = $project ? (string) $project->domain : '';
        $wmStatus = $this->webmasterStatusPayload($crawl, $domain);
        $wmReady = ! empty($wmStatus['ready']);

        // В аудите: если Вебмастер привязан — гоняем сверку даже без SITE_AUDIT_SERP_INDEX.
        $enabled = $force
            || (bool) config('site_audit.serp_index_enabled', false)
            || $wmReady;

        if (! $enabled) {
            // Не трогаем уже собранные findings — иначе список URL пропадает при «disabled».
            $this->saveProgress($crawl, [
                'skipped' => true,
                'reason' => 'disabled',
                'pages_total' => $pagesTotal,
                'webmaster' => $wmStatus,
            ], $prevDeep);

            return;
        }

        if ($pagesTotal < 1) {
            $this->saveProgress($crawl, [
                'skipped' => true,
                'reason' => 'no_pages',
                'pages_total' => $pagesTotal,
                'webmaster' => $wmStatus,
            ], $prevDeep);

            return;
        }

        if ($domain === '') {
            $this->saveProgress($crawl, [
                'skipped' => true,
                'reason' => 'no_domain',
                'pages_total' => $pagesTotal,
                'webmaster' => $wmStatus,
            ], $prevDeep);

            return;
        }

        $rootUrl = 'https://' . preg_replace('#^https?://#i', '', rtrim($domain, '/')) . '/';

        if (! $wmReady) {
            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', 'index_count_mismatch')
                ->delete();
            $this->deleteUrlMissingFindings($crawl->id);

            $this->saveProgress($crawl, [
                'skipped' => true,
                'reason' => 'no_webmaster',
                'pages_total' => $pagesTotal,
                'webmaster' => $wmStatus,
            ], null);

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'index_count_mismatch',
                'severity' => 'info',
                'url' => $rootUrl,
                'url_hash' => SiteAuditUrlNormalizer::hash($rootUrl),
                'meta_json' => [
                    'engine' => 'yandex',
                    'source' => 'none',
                    'pages_total' => $pagesTotal,
                    'needs_webmaster' => true,
                    'note' => 'Подключите Яндекс.Вебмастер и привяжите хост к домену — сверка списка выполняется при аудите',
                ],
            ]);

            return;
        }

        $result = $this->compareViaWebmaster($crawl, $domain);
        if ($result === null || empty($result['ok'])) {
            $msg = is_array($result) ? (string) ($result['message'] ?? 'ошибка Вебмастера') : 'Вебмастер недоступен';

            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', 'index_count_mismatch')
                ->delete();
            $this->deleteUrlMissingFindings($crawl->id);

            $this->saveProgress($crawl, [
                'skipped' => true,
                'reason' => 'webmaster_error',
                'error' => $msg,
                'pages_total' => $pagesTotal,
                'webmaster' => $wmStatus,
            ], null);

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'index_count_mismatch',
                'severity' => 'warning',
                'url' => $rootUrl,
                'url_hash' => SiteAuditUrlNormalizer::hash($rootUrl),
                'meta_json' => [
                    'engine' => 'yandex',
                    'source' => 'webmaster',
                    'pages_total' => $pagesTotal,
                    'host_id' => $wmStatus['host_id'] ?? null,
                    'error' => $msg,
                ],
            ]);
        }
        // Успех: findings + progress.deep пишет compareViaWebmaster → persistComparison
    }

    /**
     * Повторная сверка (кнопка в отчёте) — тот же контур, что и при аудите.
     *
     * @return array{ok:bool,message:string,matched?:int,missing_in_index?:int,extra_in_index?:int,serp_count?:int}
     */
    public function deepSample(SiteAuditCrawl $crawl, ?string $engine = null): array
    {
        $engine = strtolower((string) ($engine ?: 'yandex'));
        if ($engine !== 'yandex') {
            return [
                'ok' => false,
                'message' => 'Сверка индекса только через Яндекс.Вебмастер (Google Search Console — позже)',
            ];
        }

        $this->run($crawl, true);
        $crawl->refresh();
        $deep = is_array($crawl->progress_json['serp_index']['deep'] ?? null)
            ? $crawl->progress_json['serp_index']['deep']
            : null;

        if (is_array($deep) && isset($deep['serp_count'])) {
            return [
                'ok' => true,
                'message' => 'Вебмастер: ' . (int) $deep['serp_count'] . ' URL, совпало '
                    . (int) ($deep['matched'] ?? 0) . '/' . (int) ($deep['crawl_count'] ?? 0),
                'matched' => (int) ($deep['matched'] ?? 0),
                'missing_in_index' => (int) ($deep['missing_in_index'] ?? 0),
                'extra_in_index' => (int) ($deep['extra_in_index'] ?? 0),
                'serp_count' => (int) ($deep['serp_count'] ?? 0),
            ];
        }

        $block = is_array($crawl->progress_json['serp_index'] ?? null)
            ? $crawl->progress_json['serp_index']
            : [];

        return [
            'ok' => false,
            'message' => (string) ($block['error'] ?? 'Сверка не выполнена — проверьте Вебмастер'),
        ];
    }

    /**
     * @return array{ok:bool,message:string,matched?:int,missing_in_index?:int,extra_in_index?:int,serp_count?:int}|null
     */
    private function compareViaWebmaster(SiteAuditCrawl $crawl, string $domain): ?array
    {
        $wmCtx = $this->webmasterContext($crawl, $domain);
        if ($wmCtx === null) {
            return null;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        $maxUrls = max(100, min(50000, (int) config('site_audit.serp_index_webmaster_max', 50000)));
        $pagesTotal = (int) ($crawl->pages_total ?: 0);
        $rootUrl = 'https://' . preg_replace('#^https?://#i', '', rtrim($domain, '/')) . '/';
        $crawlMap = $this->crawlUrlMap($crawl);
        if ($crawlMap === []) {
            return ['ok' => false, 'message' => 'Нет URL краула для сверки'];
        }

        $collected = $wmCtx['service']->collectInSearchUrls(
            $wmCtx['user_id'],
            $wmCtx['host_id'],
            $maxUrls
        );
        if (! ($collected['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'Вебмастер: ' . (string) ($collected['message'] ?? 'ошибка API'),
            ];
        }

        $countRes = $wmCtx['service']->getInSearchCount($wmCtx['user_id'], $wmCtx['host_id']);
        $found = ($countRes['ok'] ?? false) && isset($countRes['count']) && $countRes['count'] !== null
            ? (int) $countRes['count']
            : (isset($collected['total_available']) ? (int) $collected['total_available'] : null);

        return $this->persistComparison($crawl, [
            'engine' => 'yandex',
            'source' => 'webmaster',
            'mode' => 'webmaster_list',
            'query' => 'webmaster:in-search',
            'host_id' => $wmCtx['host_id'],
            'found' => $found,
            'index_urls' => (array) ($collected['urls'] ?? []),
            'truncated' => ! empty($collected['truncated']),
            'pages_fetched' => (int) ($collected['pages_fetched'] ?? 0),
            'max_urls' => $maxUrls,
            'pages_total' => $pagesTotal,
            'root_url' => $rootUrl,
            'crawl_map' => $crawlMap,
        ]);
    }

    /**
     * @param array{
     *   engine:string,
     *   source:string,
     *   mode:string,
     *   query:string,
     *   host_id?:string,
     *   found:?int,
     *   index_urls:list<string>,
     *   truncated:bool,
     *   pages_fetched:?int,
     *   max_urls:int,
     *   pages_total:int,
     *   root_url:string,
     *   crawl_map:array<string,string>
     * } $ctx
     * @return array{ok:bool,message:string,matched:int,missing_in_index:int,extra_in_index:int,serp_count:int}
     */
    private function persistComparison(SiteAuditCrawl $crawl, array $ctx): array
    {
        $crawlMap = $ctx['crawl_map'];
        $serpMap = [];
        foreach ($ctx['index_urls'] as $url) {
            $key = SiteAuditUrlNormalizer::canonicalKey((string) $url);
            if ($key === null || isset($serpMap[$key])) {
                continue;
            }
            $serpMap[$key] = (string) $url;
        }

        $matched = 0;
        $missingInIndex = [];
        $extraInIndex = [];
        foreach ($crawlMap as $key => $url) {
            if (isset($serpMap[$key])) {
                $matched++;
            } else {
                $missingInIndex[] = $url;
            }
        }
        foreach ($serpMap as $key => $url) {
            if (! isset($crawlMap[$key])) {
                $extraInIndex[] = $url;
            }
        }

        $serpCount = count($serpMap);
        $crawlCount = count($crawlMap);
        $missingCount = count($missingInIndex);
        $extraCount = count($extraInIndex);
        $truncated = (bool) $ctx['truncated'];
        $found = $ctx['found'];
        $listComplete = ! $truncated && ($found === null || $found <= $serpCount);
        $ratioLow = (float) config('site_audit.serp_index_ratio_low', 0.5);
        $ratioHigh = (float) config('site_audit.serp_index_ratio_high', 3.0);
        $ratio = ($found !== null && $ctx['pages_total'] > 0)
            ? round($found / $ctx['pages_total'], 4)
            : null;

        $deep = [
            'mode' => $ctx['mode'],
            'source' => $ctx['source'],
            'engine' => $ctx['engine'],
            'query' => $ctx['query'],
            'host_id' => $ctx['host_id'] ?? null,
            'found' => $found,
            'serp_count' => $serpCount,
            'crawl_count' => $crawlCount,
            'pages_total' => $ctx['pages_total'],
            'matched' => $matched,
            'missing_in_index' => $missingCount,
            'extra_in_index' => $extraCount,
            'truncated' => $truncated,
            'list_complete' => $listComplete,
            'max_urls' => $ctx['max_urls'],
            'pages_fetched' => $ctx['pages_fetched'],
            'missing_urls' => array_slice($missingInIndex, 0, 40),
            'extra_urls' => array_slice($extraInIndex, 0, 40),
            'at' => now()->toDateTimeString(),
            'during_audit' => true,
        ];

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['serp_index'] = [
            'skipped' => false,
            'pages_total' => $ctx['pages_total'],
            'ratio_low' => $ratioLow,
            'ratio_high' => $ratioHigh,
            'source' => 'webmaster',
            'webmaster' => [
                'connected' => true,
                'host_id' => $ctx['host_id'] ?? null,
                'in_search' => $found,
            ],
            'engines' => [
                'yandex' => [
                    'source' => 'webmaster',
                    'found' => $found,
                    'host_id' => $ctx['host_id'] ?? null,
                    'ratio' => $ratio,
                    'mismatch' => $ratio !== null && ($ratio < $ratioLow || $ratio > $ratioHigh),
                    'capped' => false,
                    'matched' => $matched,
                    'serp_count' => $serpCount,
                    'crawl_count' => $crawlCount,
                ],
            ],
            'deep' => $deep,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->where('code', 'index_count_mismatch')
            ->delete();
        $this->deleteUrlMissingFindings($crawl->id);

        $severity = 'info';
        if ($listComplete && $crawlCount > 0) {
            $missRatio = $missingCount / $crawlCount;
            if ($missRatio >= 0.25 || ($found !== null && $ctx['pages_total'] > 0 && $found / $ctx['pages_total'] < 0.5)) {
                $severity = 'warning';
            }
        } elseif (! $listComplete && $extraCount > 0 && $matched < (int) ($crawlCount * 0.3)) {
            $severity = 'warning';
        }

        $urlFindingsMax = max(0, min(20000, (int) config('site_audit.serp_index_url_findings_max', 5000)));
        $missingForFindings = array_slice($missingInIndex, 0, $urlFindingsMax);

        // В таблице отчёта — только страницы не в индексе (ПС в деталях).
        // Сводка живёт в progress_json и в жёлтом блоке сверху.
        if ($missingForFindings === []) {
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'index_count_mismatch',
                'severity' => $severity,
                'url' => $ctx['root_url'],
                'url_hash' => SiteAuditUrlNormalizer::hash($ctx['root_url']),
                'meta_json' => [
                    'kind' => 'summary',
                    'engine' => $ctx['engine'],
                    'source' => $ctx['source'],
                    'pages_total' => $ctx['pages_total'],
                    'capped' => false,
                    'deep' => true,
                    'mode' => $ctx['mode'],
                    'query' => $ctx['query'],
                    'host_id' => $ctx['host_id'] ?? null,
                    'found' => $found,
                    'serp_count' => $serpCount,
                    'crawl_count' => $crawlCount,
                    'matched' => $matched,
                    'missing_in_index' => $missingCount,
                    'extra_in_index' => $extraCount,
                    'truncated' => $truncated,
                    'list_complete' => $listComplete,
                    'url_findings' => 0,
                    'url_findings_capped' => false,
                    'excluded_robots' => true,
                    'missing_urls' => [],
                    'extra_urls' => array_slice($extraInIndex, 0, 12),
                    'during_audit' => true,
                ],
            ]);
        } else {
            $this->emitMissingUrlFindings(
                $crawl->id,
                (string) $ctx['engine'],
                (string) $ctx['source'],
                $missingForFindings,
                $truncated
            );
        }

        $msg = 'Вебмастер: ' . $serpCount . ' URL'
            . ($found !== null ? (' (в поиске ~' . $found . ')') : '')
            . ', совпало с краулом ' . $matched . '/' . $crawlCount;
        if ($missingCount > 0) {
            $msg .= ', в крауле нет в индексе: ' . $missingCount;
        }
        if ($extraCount > 0) {
            $msg .= ', в индексе нет в крауле: ' . $extraCount;
        }
        if ($truncated) {
            $msg .= ' (список обрезан, сверка частичная)';
        }

        return [
            'ok' => true,
            'message' => $msg,
            'matched' => $matched,
            'missing_in_index' => $missingCount,
            'extra_in_index' => $extraCount,
            'serp_count' => $serpCount,
        ];
    }

    /**
     * URL-находки в том же отчёте index_count_mismatch (не отдельный code).
     *
     * @param list<string> $urls
     */
    private function emitMissingUrlFindings(
        int $crawlId,
        string $engine,
        string $source,
        array $urls,
        bool $listTruncated
    ): void {
        if ($urls === []) {
            return;
        }

        $cfg = config('site_audit.findings.index_count_mismatch', []);
        $severity = (string) ($cfg['severity'] ?? 'warning');
        $now = now()->toDateTimeString();
        $rows = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }
            $rows[] = [
                'crawl_id' => $crawlId,
                'code' => 'index_count_mismatch',
                'severity' => $severity,
                'url' => $url,
                'url_hash' => SiteAuditUrlNormalizer::hash($url),
                'meta_json' => json_encode([
                    'kind' => 'missing_url',
                    'engine' => $engine,
                    'source' => $source,
                    'reason' => 'not_in_search',
                    'excluded_robots' => true,
                    'list_truncated' => $listTruncated,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($rows) >= 200) {
                SiteAuditFinding::query()->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            SiteAuditFinding::query()->insert($rows);
        }
    }

    /** Устаревшие code индексации — подчистка при пересверке Вебмастером. */
    private function deleteUrlMissingFindings(int $crawlId): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->whereIn('code', ['index_url_missing', 'serp_not_indexed'])
            ->delete();
    }

    /**
     * @return array{user_id:int,host_id:string,service:YandexWebmasterService}|null
     */
    private function webmasterContext(SiteAuditCrawl $crawl, string $domain): ?array
    {
        if (! (bool) config('site_audit.serp_index_prefer_webmaster', true)) {
            return null;
        }

        $userId = (int) $crawl->user_id;
        if ($userId < 1) {
            return null;
        }

        /** @var YandexWebmasterService $service */
        $service = app(YandexWebmasterService::class);
        if (! $service->isConfigured() || ! $service->isConnected($userId)) {
            return null;
        }

        $hostId = SeoReportBindings::resolveWebmasterHost($userId, $domain);
        if ($hostId === null || $hostId === '') {
            return null;
        }

        return [
            'user_id' => $userId,
            'host_id' => $hostId,
            'service' => $service,
        ];
    }

    /**
     * @return array{configured:bool,connected:bool,host_id:?string,domain:string,ready:bool}
     */
    public function webmasterStatusPayload(SiteAuditCrawl $crawl, ?string $domain = null): array
    {
        if ($domain === null || $domain === '') {
            $project = SiteAuditProject::query()->find($crawl->project_id);
            $domain = $project ? (string) $project->domain : '';
        }
        $userId = (int) $crawl->user_id;
        /** @var YandexWebmasterService $service */
        $service = app(YandexWebmasterService::class);
        $connected = $userId > 0 && $service->isConfigured() && $service->isConnected($userId);
        $hostId = $connected ? SeoReportBindings::resolveWebmasterHost($userId, $domain) : null;

        return [
            'configured' => $service->isConfigured(),
            'connected' => $connected,
            'host_id' => $hostId,
            'domain' => HomeUserSites::normalizeDomain($domain),
            'ready' => $connected && $hostId !== null && $hostId !== '',
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed>|null $prevDeep
     */
    private function saveProgress(SiteAuditCrawl $crawl, array $payload, ?array $prevDeep): void
    {
        if ($prevDeep !== null) {
            $payload['deep'] = $prevDeep;
        }
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['serp_index'] = $payload;
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    /**
     * Страницы краула для сверки: 200, без редиректа, без noindex, без robots Disallow.
     *
     * @return array<string, string> canonicalKey => url
     */
    private function crawlUrlMap(SiteAuditCrawl $crawl): array
    {
        $robotsBlocked = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->where('code', 'robots_blocked')
            ->pluck('url_hash')
            ->all();
        $robotsBlocked = array_fill_keys(array_map('strval', $robotsBlocked), true);

        $robotsGroups = null;
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        if (isset($progress['robots']['groups']) && is_array($progress['robots']['groups'])) {
            $robotsGroups = $progress['robots']['groups'];
        }
        $robots = $robotsGroups !== null ? new SiteAuditRobotsTxt() : null;

        $rows = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where('status_code', 200)
            ->where(function ($q) {
                $q->whereNull('redirect_chain')
                    ->orWhere('redirect_chain', '[]')
                    ->orWhere('redirect_chain', '');
            })
            ->where(function ($q) {
                $q->whereNull('noindex')->orWhere('noindex', 0)->orWhere('noindex', false);
            })
            ->orderBy('id')
            ->get(['url', 'url_hash']);

        $map = [];
        foreach ($rows as $row) {
            $url = trim((string) $row->url);
            if ($url === '') {
                continue;
            }
            $hash = (string) ($row->url_hash ?? '');
            if ($hash !== '' && isset($robotsBlocked[$hash])) {
                continue;
            }
            if ($robots !== null && ! $robots->isPathAllowed($robotsGroups, $url)) {
                continue;
            }
            $key = SiteAuditUrlNormalizer::canonicalKey($url);
            if ($key === null || isset($map[$key])) {
                continue;
            }
            $map[$key] = $url;
        }

        return $map;
    }
}
