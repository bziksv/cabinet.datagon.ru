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
            $robotsGroups = is_array($crawl->progress_json['robots']['groups'] ?? null)
                ? $crawl->progress_json['robots']['groups']
                : null;

            // сразу снять огромный engine из MySQL (миграция со старых краулов)
            if (isset($crawl->progress_json['engine'])) {
                $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded);
                $crawl->save();
            }

            if ($crawl->status !== SiteAuditCrawl::STATUS_FETCHING) {
                $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
                $crawl->save();
            }

            $processor = new SiteAuditPageProcessor();
            $processed = 0;

            while ($i < count($queue)) {
                if ($processed >= $maxPages || (microtime(true) - $startedAt) >= $maxSeconds) {
                    break;
                }

                if ($this->crawlStatusIsTerminal((int) $crawl->id)) {
                    $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded);

                    return false;
                }

                $url = $queue[$i];
                $i++;
                $fetched++;
                $processed++;

                try {
                    $out = $processor->process($crawl->id, $url, $host, $settings);
                } catch (\Throwable $e) {
                    Log::warning('SiteAudit page process failed', [
                        'crawl_id' => $crawl->id,
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                    $out = ['internal_links' => [], 'content_unchanged' => false];
                }

                if (! empty($out['content_unchanged'])) {
                    $unchanged++;
                }

                if ($this->crawlStatusIsTerminal((int) $crawl->id)) {
                    $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded);

                    return false;
                }

                if (! empty($out['internal_links'])) {
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
                        if (is_array($robotsGroups) && ! (new SiteAuditRobotsTxt())->isPathAllowed($robotsGroups, $link)) {
                            continue;
                        }
                        $seen[$link] = true;
                        $queue[] = $link;
                        $expanded++;
                        $known++;
                    }
                }

                $pagesTotal = max(count($seen), $fetched + (count($queue) - $i));
                if ($processed % self::PROGRESS_DB_EVERY === 0 || $processed === 1) {
                    $this->touchProgress($crawl, $fetched, $pagesTotal, $unchanged, $expanded);
                }
                if ($processed % self::ENGINE_FILE_EVERY === 0) {
                    $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded);
                }
            }

            // закончили всю очередь
            if ($i >= count($queue)) {
                $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded);
                $this->finishFetch($crawl, $dispatchContinue);

                return false;
            }

            // ещё есть URL — следующий тик
            $pagesTotal = max(count($seen), $fetched + (count($queue) - $i));
            $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded);
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

        $seed = [];
        $manual = $project->setting('seed_urls', []);
        if (is_array($manual)) {
            foreach ($manual as $u) {
                $norm = SiteAuditUrlNormalizer::normalize((string) $u, $project->domain, $urlOpts);
                if ($norm) {
                    $seed[$norm] = true;
                }
            }
        }

        $home = SiteAuditUrlNormalizer::normalize('https://' . $project->domain . '/', $project->domain, $urlOpts);
        if ($home) {
            $seed[$home] = true;
        }

        $extraHosts = SiteAuditUrlNormalizer::parseExtraHosts($settings['extra_hosts'] ?? []);
        foreach ($extraHosts as $extraHost) {
            $extraHome = SiteAuditUrlNormalizer::normalize('https://' . $extraHost . '/', $extraHost, $urlOpts);
            if ($extraHome) {
                $seed[$extraHome] = true;
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
                $seed[$norm] = true;
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
        $this->persistEngineState($crawl, $queue, 0, 0, $seen, 0, 0);
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
     * Лёгкий апдейт счётчиков в БД — без очереди URL в progress_json.
     */
    private function touchProgress(
        SiteAuditCrawl $crawl,
        int $fetched,
        int $pagesTotal,
        int $unchanged,
        int $expanded
    ): void {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine']);
        $progress['engine_storage'] = 'file';
        $progress['fetched'] = $fetched;
        $progress['total'] = $pagesTotal;
        $progress['pages_unchanged'] = $unchanged;
        $progress['links_expanded'] = $expanded;
        $crawl->progress_json = $progress;
        $crawl->pages_fetched = $fetched;
        $crawl->pages_total = $pagesTotal;
        $crawl->save();
    }

    private function engineStatePath(int $crawlId): string
    {
        $dir = storage_path('app/site-audit-engine');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/crawl_' . $crawlId . '.json';
    }

    private function hasEngineState(SiteAuditCrawl $crawl): bool
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
     * @return array{queue: string[], index: int, fetched: int, seen: array<string,bool>, unchanged: int, expanded: int}
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

        return [
            'queue' => $queue,
            'index' => $index,
            'fetched' => max(0, $fetched),
            'seen' => $seen,
            'unchanged' => (int) ($engine['unchanged'] ?? 0),
            'expanded' => (int) ($engine['expanded'] ?? 0),
        ];
    }

    /**
     * @param string[] $queue
     * @param array<string,bool> $seen
     */
    private function persistEngineState(
        SiteAuditCrawl $crawl,
        array $queue,
        int $index,
        int $fetched,
        array $seen,
        int $unchanged,
        int $expanded
    ): void {
        $remaining = array_values(array_slice($queue, max(0, $index)));
        $payload = [
            'queue' => $remaining,
            'index' => 0,
            'fetched' => $fetched,
            'seen' => array_keys($seen),
            'unchanged' => $unchanged,
            'expanded' => $expanded,
        ];

        $path = $this->engineStatePath((int) $crawl->id);
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @rename($tmp, $path);

        // убираем blob очереди из MySQL — иначе 6MB UPDATE на каждый тик
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine']);
        $progress['engine_storage'] = 'file';
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
        unset($progress['engine'], $progress['engine_storage']);
        $crawl->progress_json = $progress;
    }
}
