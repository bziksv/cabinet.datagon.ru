<?php

namespace App\Services\SiteAudit;

use App\Jobs\SiteAudit\AggregateSiteAuditCrawlJob;
use App\Jobs\SiteAudit\ContinueSiteAuditCrawlJob;
use App\SiteAuditCrawl;
use App\SiteAuditProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Полный прогон краула: sitemap + дообход по внутренним ссылкам до лимита.
 *
 * Асинхронно — порциями (batch): после лимита страниц/секунд ставится ContinueSiteAuditCrawlJob,
 * чтобы worker timeout (раньше 1ч) не убивал краул на середине.
 */
class SiteAuditCrawlEngine
{
    /** Как часто писать лёгкий прогресс в БД (без очереди URL). */
    private const PROGRESS_DB_EVERY = 5;

    /** Как часто сбрасывать очередь/seen на диск. */
    private const ENGINE_FILE_EVERY = 15;

    public function run(SiteAuditCrawl $crawl, bool $asyncChunks = true): SiteAuditCrawl
    {
        if (! $this->hasEngineState($crawl)) {
            $crawl = $this->discoverAndInitQueue($crawl);
            $crawl->refresh();
            if ($crawl->isFinished()) {
                return $crawl;
            }
            if (! $this->hasEngineState($crawl)) {
                return $crawl;
            }
        }

        if ($asyncChunks) {
            $this->processBatch($crawl);

            return $crawl->fresh() ?: $crawl;
        }

        // sync: крутим батчи в одном процессе до конца
        $guard = 0;
        while ($guard++ < 100000) {
            $crawl->refresh();
            if ($crawl->isFinished()) {
                return $crawl;
            }
            if (! $this->hasEngineState($crawl)) {
                return $crawl;
            }
            $more = $this->processBatch($crawl, false);
            if (! $more) {
                return $crawl->fresh() ?: $crawl;
            }
        }

        $crawl->status = SiteAuditCrawl::STATUS_FAILED;
        $crawl->error = 'Прерван: слишком много батчей (внутренняя защита)';
        $crawl->finished_at = now();
        $crawl->save();
        SiteAuditGlobalCap::promoteWaiting();

        return $crawl->fresh() ?: $crawl;
    }

    /**
     * Один тик обхода. @return bool true если ещё есть URL и нужен следующий тик
     */
    public function processBatch(SiteAuditCrawl $crawl, bool $dispatchContinue = true): bool
    {
        $lockKey = 'site_audit_crawl_tick_' . $crawl->id;
        $lockTtl = max(120, (int) config('site_audit.batch_max_seconds', 240) + 180);
        if (! Cache::add($lockKey, 1, $lockTtl)) {
            return true; // другой воркер уже крутит — Continue уже в работе / в очереди
        }

        try {
            $crawl->refresh();
            if ($crawl->isFinished() || $crawl->status === SiteAuditCrawl::STATUS_CANCELLED) {
                return false;
            }
            if ($crawl->status === SiteAuditCrawl::STATUS_QUEUED_WAIT) {
                return false;
            }
            if (! $this->hasEngineState($crawl)) {
                return false;
            }

            $maxPages = max(1, (int) config('site_audit.batch_max_pages', 80));
            $maxSeconds = max(30, (int) config('site_audit.batch_max_seconds', 240));
            $startedAt = microtime(true);

            $project = SiteAuditProject::query()->find($crawl->project_id);
            if (! $project) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Project not found';
                $crawl->finished_at = now();
                $this->clearEngineState($crawl);
                $crawl->save();
                SiteAuditGlobalCap::promoteWaiting();

                return false;
            }

            $settings = array_merge(
                $project->settings_json ?? [],
                is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : []
            );
            $limit = max(1, (int) $crawl->pages_limit);
            $host = SiteAuditUrlNormalizer::hostOf('https://' . $project->domain) ?: $project->domain;
            $patterns = SiteAuditUrlFilter::parsePatterns(
                $settings['exclude_patterns'] ?? $project->setting('exclude_patterns', [])
            );

            $state = $this->loadEngineState($crawl);
            $queue = $state['queue'];
            $i = $state['index'];
            $fetched = $state['fetched'];
            $seen = $state['seen'];
            $unchanged = $state['unchanged'];
            $expanded = $state['expanded'];
            $origins = $state['origins'];
            $robotsGroups = is_array($crawl->progress_json['robots']['groups'] ?? null)
                ? $crawl->progress_json['robots']['groups']
                : null;

            // сразу снять огромный engine / urls_gz из MySQL (миграция mid-crawl)
            $dirtyProgress = false;
            if (isset($crawl->progress_json['engine'])) {
                $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                $dirtyProgress = true;
            }
            if (! empty($crawl->progress_json['sitemap']['urls_gz'])) {
                $this->offloadSitemapUrlsGz($crawl);
                $dirtyProgress = true;
            }
            if ($dirtyProgress) {
                $crawl->save();
            }

            if ($crawl->status !== SiteAuditCrawl::STATUS_FETCHING) {
                $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
                $crawl->save();
            }

            $processor = new SiteAuditPageProcessor();
            $robotsTxt = is_array($robotsGroups) ? new SiteAuditRobotsTxt() : null;
            $processed = 0;
            $maxConcurrency = max(1, (int) config('site_audit.max_concurrency', 8));
            $concurrency = max(1, min($maxConcurrency, (int) ($settings['concurrency'] ?? 1)));

            while ($i < count($queue)) {
                if ($processed >= $maxPages || (microtime(true) - $startedAt) >= $maxSeconds) {
                    break;
                }

                // cancel/fail — редко; не долбим MySQL на каждом URL
                if ($processed === 0 || $processed % 10 === 0) {
                    if ($this->crawlStatusIsTerminal((int) $crawl->id)) {
                        $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);

                        return false;
                    }
                }

                $remainingBudget = $maxPages - $processed;
                $waveSize = min($concurrency, $remainingBudget, count($queue) - $i);
                if ($waveSize < 1) {
                    break;
                }

                $wave = [];
                for ($w = 0; $w < $waveSize; $w++) {
                    $wave[] = $queue[$i];
                    $i++;
                }

                $waveOrigins = [];
                foreach ($wave as $wu) {
                    if (isset($origins[$wu]) && is_array($origins[$wu])) {
                        $waveOrigins[$wu] = $origins[$wu];
                    }
                }

                try {
                    $outs = $processor->processMany($crawl->id, $wave, $host, $settings, $waveOrigins);
                } catch (\Throwable $e) {
                    Log::warning('SiteAudit wave process failed', [
                        'crawl_id' => $crawl->id,
                        'urls' => count($wave),
                        'error' => $e->getMessage(),
                    ]);
                    $outs = array_fill(0, count($wave), ['internal_links' => [], 'content_unchanged' => false]);
                }

                foreach ($wave as $wi => $url) {
                    $fetched++;
                    $processed++;
                    $out = $outs[$wi] ?? ['internal_links' => [], 'content_unchanged' => false];

                    if (! empty($out['content_unchanged'])) {
                        $unchanged++;
                    }

                    if (! empty($out['internal_links']) && empty($settings['pages_only'])) {
                        $known = $fetched + (count($queue) - $i);
                        foreach ($out['internal_links'] as $link) {
                            if (isset($seen[$link])) {
                                continue;
                            }
                            if ($known >= $limit) {
                                break;
                            }
                            if ($patterns && SiteAuditUrlFilter::isExcluded($link, $patterns)) {
                                continue;
                            }
                            if ($robotsTxt && ! $robotsTxt->isPathAllowed($robotsGroups, $link)) {
                                continue;
                            }
                            $seen[$link] = true;
                            $queue[] = $link;
                            if (! isset($origins[$link])) {
                                $origins[$link] = ['via' => 'link', 'from' => $url];
                            }
                            $expanded++;
                            $known++;
                        }
                    }

                    $pagesTotal = max(count($seen), $fetched + (count($queue) - $i));
                    if ($processed % self::PROGRESS_DB_EVERY === 0 || $processed === 1) {
                        $this->touchProgress($crawl, $fetched, $pagesTotal, $unchanged, $expanded);
                    }
                    if ($processed % self::ENGINE_FILE_EVERY === 0) {
                        $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                    }
                }
            }

            // закончили всю очередь
            if ($i >= count($queue)) {
                $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                $this->finishFetch($crawl, $dispatchContinue);

                return false;
            }

            // ещё есть URL — следующий тик
            $pagesTotal = max(count($seen), $fetched + (count($queue) - $i));
            $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
            $this->touchProgress($crawl, $fetched, $pagesTotal, $unchanged, $expanded);

            if ($dispatchContinue) {
                ContinueSiteAuditCrawlJob::dispatch($crawl->id);
            }

            return true;
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function discoverAndInitQueue(SiteAuditCrawl $crawl): SiteAuditCrawl
    {
        $project = SiteAuditProject::query()->find($crawl->project_id);
        if (! $project) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'Project not found';
            $crawl->finished_at = now();
            $crawl->save();
            SiteAuditGlobalCap::promoteWaiting();

            return $crawl;
        }

        $crawl->status = SiteAuditCrawl::STATUS_DISCOVERING;
        $crawl->started_at = $crawl->started_at ?: now();
        $crawl->save();

        try {
            (new SiteAuditRobotsProbe())->run($crawl, $project->domain);
            $crawl->refresh();
        } catch (\Throwable $e) {
            // optional
        }

        try {
            (new SiteAuditHostVariantProbe())->run($crawl, $project->domain);
            $crawl->refresh();
        } catch (\Throwable $e) {
            // optional — не валим краул из‑за проверки зеркал
        }

        $settings = array_merge(
            $project->settings_json ?? [],
            is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : []
        );

        $limit = max(1, (int) $crawl->pages_limit);
        $patterns = SiteAuditUrlFilter::parsePatterns(
            $settings['exclude_patterns'] ?? $project->setting('exclude_patterns', [])
        );

        $urlOpts = SiteAuditUrlNormalizer::optionsFromSettings($settings, $project->domain);
        $pagesOnly = ! empty($settings['pages_only']);

        /** @var array<string, true> $seed */
        $seed = [];
        /** @var array<string, array{via:string,from:?string}> $origins */
        $origins = [];
        $markOrigin = static function (string $url, string $via, ?string $from = null) use (&$seed, &$origins): void {
            $seed[$url] = true;
            if (! isset($origins[$url])) {
                $origins[$url] = ['via' => $via, 'from' => $from];
            }
        };

        $manual = $project->setting('seed_urls', []);
        if (is_array($manual)) {
            foreach ($manual as $u) {
                $norm = SiteAuditUrlNormalizer::normalize((string) $u, $project->domain, $urlOpts);
                if ($norm) {
                    $markOrigin($norm, 'seed');
                }
            }
        }

        $home = SiteAuditUrlNormalizer::normalize('https://' . $project->domain . '/', $project->domain, $urlOpts);

        if ($pagesOnly) {
            // только явные URL: без главной «насильно», без sitemap, без дообхода
            if ($seed === []) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Режим «только страницы»: укажите хотя бы один URL';
                $crawl->finished_at = now();
                $crawl->save();
                SiteAuditGlobalCap::promoteWaiting();

                return $crawl;
            }
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['pages_only'] = true;
            $progress['sitemap'] = [
                'found' => false,
                'url_count' => 0,
                'seed_count' => count($seed),
                'sources' => [],
                'errors' => [],
                'skipped' => 'pages_only',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();
        } else {
            if ($home) {
                $markOrigin($home, 'home');
            }

            $extraHosts = SiteAuditUrlNormalizer::parseExtraHosts($settings['extra_hosts'] ?? []);
            foreach ($extraHosts as $extraHost) {
                $extraHome = SiteAuditUrlNormalizer::normalize('https://' . $extraHost . '/', $extraHost, $urlOpts);
                if ($extraHome) {
                    $markOrigin($extraHome, 'home');
                }
            }
            if ($extraHosts !== []) {
                $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
                $progress['extra_hosts'] = $extraHosts;
                $crawl->progress_json = $progress;
                $crawl->save();
            }

            try {
                $discovered = (new SiteAuditSitemapProbe())->run($crawl, $project->domain, $limit);
                $crawl->refresh();
                foreach ($discovered['seed_urls'] as $u) {
                    $norm = SiteAuditUrlNormalizer::normalize($u, $project->domain, $urlOpts) ?: $u;
                    $markOrigin($norm, 'sitemap');
                }
            } catch (\Throwable $e) {
                if (! $seed) {
                    $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                    $crawl->error = 'Discovery failed: ' . $e->getMessage();
                    $crawl->finished_at = now();
                    $crawl->save();
                    SiteAuditGlobalCap::promoteWaiting();

                    return $crawl;
                }
            }
        }

        $queue = array_keys($seed);
        if ($patterns) {
            $before = count($queue);
            $queue = SiteAuditUrlFilter::filterList($queue, $patterns);
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['excluded'] = max(0, $before - count($queue));
            $crawl->progress_json = $progress;
        }

        $groups = $crawl->progress_json['robots']['groups'] ?? null;
        if (is_array($groups) && $groups !== []) {
            $robotsTxt = new SiteAuditRobotsTxt();
            $before = count($queue);
            $queue = array_values(array_filter($queue, function ($u) use ($robotsTxt, $groups, $home) {
                if ($home && $u === $home) {
                    return true;
                }

                return $robotsTxt->isPathAllowed($groups, $u);
            }));
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['robots_skipped'] = max(0, $before - count($queue));
            $crawl->progress_json = $progress;
        }

        $queue = array_slice($queue, 0, $limit);
        $origins = array_intersect_key($origins, array_flip($queue));

        $seen = [];
        foreach ($queue as $u) {
            $seen[$u] = true;
        }

        $crawl->pages_total = count($queue);
        $crawl->pages_fetched = 0;
        $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['fetched'] = 0;
        $progress['total'] = count($queue);
        $progress['links_expanded'] = 0;
        $crawl->progress_json = $progress;
        $this->persistEngineState($crawl, $queue, 0, 0, $seen, 0, 0, $origins);
        $this->offloadSitemapUrlsGz($crawl);
        $crawl->save();

        if (! $queue) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'No URLs discovered';
            $crawl->finished_at = now();
            $this->clearEngineState($crawl);
            $crawl->save();
            SiteAuditGlobalCap::promoteWaiting();
        }

        return $crawl;
    }

    private function finishFetch(SiteAuditCrawl $crawl, bool $queueAggregate = true): void
    {
        SiteAuditUserAgentSession::clear($crawl->id);
        $this->clearEngineState($crawl);
        $crawl->save();

        $crawl->refresh();
        if ($crawl->status === SiteAuditCrawl::STATUS_CANCELLED) {
            $crawl->finished_at = $crawl->finished_at ?: now();
            $crawl->save();
            SiteAuditGlobalCap::promoteWaiting();

            return;
        }

        if ($crawl->isFinished()) {
            SiteAuditGlobalCap::promoteWaiting();

            return;
        }

        if ($queueAggregate) {
            AggregateSiteAuditCrawlJob::dispatch($crawl->id);
        } else {
            (new AggregateSiteAuditCrawlJob($crawl->id))->handle();
        }
    }

    private function crawlStatusIsTerminal(int $crawlId): bool
    {
        $status = \Illuminate\Support\Facades\DB::table('site_audit_crawls')
            ->where('id', $crawlId)
            ->value('status');

        return in_array($status, [
            SiteAuditCrawl::STATUS_DONE,
            SiteAuditCrawl::STATUS_FAILED,
            SiteAuditCrawl::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Лёгкий апдейт счётчиков в БД — без перезаписи всего progress_json (urls_gz и т.п.).
     */
    private function touchProgress(
        SiteAuditCrawl $crawl,
        int $fetched,
        int $pagesTotal,
        int $unchanged,
        int $expanded
    ): void {
        $crawl->pages_fetched = $fetched;
        $crawl->pages_total = $pagesTotal;

        \Illuminate\Support\Facades\DB::table('site_audit_crawls')
            ->where('id', $crawl->id)
            ->update([
                'pages_fetched' => $fetched,
                'pages_total' => $pagesTotal,
                'updated_at' => now(),
                'progress_json' => \Illuminate\Support\Facades\DB::raw(
                    "JSON_SET(COALESCE(progress_json, '{}'),"
                    . " '$.engine_storage', 'file',"
                    . " '$.fetched', " . (int) $fetched . ","
                    . " '$.total', " . (int) $pagesTotal . ","
                    . " '$.pages_unchanged', " . (int) $unchanged . ","
                    . " '$.links_expanded', " . (int) $expanded
                    . ")"
                ),
            ]);
    }

    private function engineStatePath(int $crawlId): string
    {
        $dir = storage_path('app/site-audit-engine');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/crawl_' . $crawlId . '.json';
    }

    /**
     * urls_gz (~сотня KB–MB) не должен жить в progress_json во время fetch —
     * иначе каждый save/refresh таскает его из MySQL.
     */
    private function offloadSitemapUrlsGz(SiteAuditCrawl $crawl): void
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $gz = $progress['sitemap']['urls_gz'] ?? null;
        if (! is_string($gz) || $gz === '') {
            return;
        }

        $dir = storage_path('app/site-audit-engine');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/crawl_' . (int) $crawl->id . '_sitemap_urls.b64';
        file_put_contents($path, $gz);
        unset($progress['sitemap']['urls_gz']);
        $progress['sitemap']['urls_gz_file'] = true;
        $crawl->progress_json = $progress;
    }

    public function hasEngineState(SiteAuditCrawl $crawl): bool
    {
        $path = $this->engineStatePath((int) $crawl->id);
        if (is_file($path) && filesize($path) > 2) {
            return true;
        }

        $engine = $crawl->progress_json['engine'] ?? null;

        return is_array($engine)
            && isset($engine['queue'], $engine['index'])
            && is_array($engine['queue']);
    }

    /**
     * Можно ли продолжить после failed/cancelled с сохранённой очередью.
     */
    public function canResume(SiteAuditCrawl $crawl): bool
    {
        if (! in_array($crawl->status, [
            SiteAuditCrawl::STATUS_FAILED,
            SiteAuditCrawl::STATUS_CANCELLED,
        ], true)) {
            return false;
        }

        // Лёгкий флаг в progress_json (история не грузит 5MB queue на каждый ряд).
        $meta = $this->engineResumeMeta($crawl);
        if ($meta !== null) {
            return (int) ($meta['remaining'] ?? 0) > 0;
        }

        if (! $this->hasEngineState($crawl)) {
            return false;
        }

        $state = $this->loadEngineState($crawl);

        return count($state['queue']) > $state['index'];
    }

    /**
     * @return array{remaining?:int,fetched?:int}|null
     */
    private function engineResumeMeta(SiteAuditCrawl $crawl): ?array
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : null;
        if ($progress === null && isset($crawl->engine_resume_raw)) {
            $raw = $crawl->engine_resume_raw;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);

                return is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw)) {
                return $raw;
            }
        }
        if (! is_array($progress)) {
            return null;
        }
        $meta = $progress['engine_resume'] ?? null;

        return is_array($meta) ? $meta : null;
    }

    /**
     * Снять finished и снова поставить в очередь (тот же crawl_id, тот же прогресс).
     */
    public function resume(SiteAuditCrawl $crawl): SiteAuditCrawl
    {
        if (! $this->canResume($crawl)) {
            throw new \RuntimeException('Нет сохранённого прогресса для продолжения — только полный повтор');
        }

        $crawl->status = SiteAuditCrawl::STATUS_QUEUED_WAIT;
        $crawl->error = null;
        $crawl->finished_at = null;
        $crawl->save();

        SiteAuditGlobalCap::tryDispatch($crawl);

        return $crawl->fresh() ?: $crawl;
    }

    public static function clearStoredState(int $crawlId): void
    {
        $dir = storage_path('app/site-audit-engine');
        foreach ([
            $dir . '/crawl_' . $crawlId . '.json',
            $dir . '/crawl_' . $crawlId . '.json.tmp',
            $dir . '/crawl_' . $crawlId . '_sitemap_urls.b64',
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{queue: string[], index: int, fetched: int, seen: array<string,bool>, unchanged: int, expanded: int, origins: array<string, array{via:string,from:?string}>}
     */
    private function loadEngineState(SiteAuditCrawl $crawl): array
    {
        $path = $this->engineStatePath((int) $crawl->id);
        $engine = null;
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $engine = $decoded;
            }
        }

        // миграция со старого огромного progress_json.engine
        if ($engine === null) {
            $engine = is_array($crawl->progress_json['engine'] ?? null) ? $crawl->progress_json['engine'] : [];
        }

        $queue = array_values(array_map('strval', is_array($engine['queue'] ?? null) ? $engine['queue'] : []));
        $index = max(0, (int) ($engine['index'] ?? 0));
        $fetched = (int) ($engine['fetched'] ?? $index);
        if ($index > 0 && $index <= count($queue)) {
            // компактим «уже обойдённые» URL из старого формата
            $queue = array_values(array_slice($queue, $index));
            $index = 0;
        } elseif ($index > count($queue)) {
            $index = 0;
        }

        $seenList = is_array($engine['seen'] ?? null) ? $engine['seen'] : [];
        $seen = [];
        foreach ($seenList as $u) {
            $seen[(string) $u] = true;
        }
        foreach ($queue as $u) {
            $seen[$u] = true;
        }

        $origins = [];
        $rawOrigins = is_array($engine['origins'] ?? null) ? $engine['origins'] : [];
        foreach ($rawOrigins as $u => $row) {
            if (! is_array($row)) {
                continue;
            }
            $via = (string) ($row['via'] ?? '');
            if ($via === '') {
                continue;
            }
            $from = isset($row['from']) && is_string($row['from']) && $row['from'] !== ''
                ? $row['from']
                : null;
            $origins[(string) $u] = ['via' => $via, 'from' => $from];
        }

        return [
            'queue' => $queue,
            'index' => $index,
            'fetched' => max(0, $fetched),
            'seen' => $seen,
            'unchanged' => (int) ($engine['unchanged'] ?? 0),
            'expanded' => (int) ($engine['expanded'] ?? 0),
            'origins' => $origins,
        ];
    }

    /**
     * @param string[] $queue
     * @param array<string,bool> $seen
     * @param array<string, array{via:string,from:?string}> $origins
     */
    private function persistEngineState(
        SiteAuditCrawl $crawl,
        array $queue,
        int $index,
        int $fetched,
        array $seen,
        int $unchanged,
        int $expanded,
        array $origins = []
    ): void {
        $remaining = array_values(array_slice($queue, max(0, $index)));
        $originsPersist = [];
        foreach ($remaining as $u) {
            if (isset($origins[$u]) && is_array($origins[$u])) {
                $originsPersist[$u] = [
                    'via' => (string) ($origins[$u]['via'] ?? ''),
                    'from' => isset($origins[$u]['from']) && is_string($origins[$u]['from']) && $origins[$u]['from'] !== ''
                        ? $origins[$u]['from']
                        : null,
                ];
            }
        }
        $payload = [
            'queue' => $remaining,
            'index' => 0,
            'fetched' => $fetched,
            'seen' => array_keys($seen),
            'unchanged' => $unchanged,
            'expanded' => $expanded,
            'origins' => $originsPersist,
        ];

        $path = $this->engineStatePath((int) $crawl->id);
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @rename($tmp, $path);

        // убираем blob очереди из MySQL — иначе 6MB UPDATE на каждый тик
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine']);
        $progress['engine_storage'] = 'file';
        $progress['engine_resume'] = [
            'remaining' => count($remaining),
            'fetched' => $fetched,
        ];
        $progress['fetched'] = $fetched;
        $progress['total'] = max(count($seen), $fetched + count($remaining));
        $progress['pages_unchanged'] = $unchanged;
        $progress['links_expanded'] = $expanded;
        $crawl->progress_json = $progress;
        $crawl->pages_fetched = $fetched;
        $crawl->pages_total = (int) $progress['total'];
    }

    private function clearEngineState(SiteAuditCrawl $crawl): void
    {
        $path = $this->engineStatePath((int) $crawl->id);
        if (is_file($path)) {
            @unlink($path);
        }
        $tmp = $path . '.tmp';
        if (is_file($tmp)) {
            @unlink($tmp);
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine'], $progress['engine_storage'], $progress['engine_resume']);
        $crawl->progress_json = $progress;
    }
}