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
            $seen = $state['seen'];
            $unchanged = $state['unchanged'];
            $expanded = $state['expanded'];

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

                $crawl->refresh();
                if ($crawl->isFinished()) {
                    $this->persistEngineState($crawl, $queue, $i, $seen, $unchanged, $expanded);

                    return false;
                }

                $url = $queue[$i];
                $i++;
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

                $crawl->refresh();
                if ($crawl->isFinished()) {
                    $this->persistEngineState($crawl, $queue, $i, $seen, $unchanged, $expanded);

                    return false;
                }

                if (! empty($out['internal_links'])) {
                    foreach ($out['internal_links'] as $link) {
                        if (isset($seen[$link])) {
                            continue;
                        }
                        if (count($queue) >= $limit) {
                            break;
                        }
                        if ($patterns && SiteAuditUrlFilter::isExcluded($link, $patterns)) {
                            continue;
                        }
                        $groups = $crawl->progress_json['robots']['groups'] ?? null;
                        if (is_array($groups) && ! (new SiteAuditRobotsTxt())->isPathAllowed($groups, $link)) {
                            continue;
                        }
                        $seen[$link] = true;
                        $queue[] = $link;
                        $expanded++;
                    }
                }

                $crawl->pages_fetched = $i;
                $crawl->pages_total = count($queue);
                $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
                $progress['fetched'] = $i;
                $progress['total'] = count($queue);
                $progress['pages_unchanged'] = $unchanged;
                $progress['links_expanded'] = $expanded;
                $crawl->progress_json = $progress;
                $this->persistEngineState($crawl, $queue, $i, $seen, $unchanged, $expanded);
                $crawl->save();
            }

            // закончили всю очередь
            if ($i >= count($queue)) {
                $this->finishFetch($crawl, $dispatchContinue);

                return false;
            }

            // ещё есть URL — следующий тик
            $this->persistEngineState($crawl, $queue, $i, $seen, $unchanged, $expanded);
            $crawl->pages_fetched = $i;
            $crawl->pages_total = count($queue);
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['fetched'] = $i;
            $progress['total'] = count($queue);
            $progress['pages_unchanged'] = $unchanged;
            $progress['links_expanded'] = $expanded;
            $crawl->progress_json = $progress;
            $crawl->save();

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
        $this->persistEngineState($crawl, $queue, 0, $seen, 0, 0);
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

    private function hasEngineState(SiteAuditCrawl $crawl): bool
    {
        $engine = $crawl->progress_json['engine'] ?? null;

        return is_array($engine)
            && isset($engine['queue'], $engine['index'])
            && is_array($engine['queue']);
    }

    /**
     * @return array{queue: string[], index: int, seen: array<string,bool>, unchanged: int, expanded: int}
     */
    private function loadEngineState(SiteAuditCrawl $crawl): array
    {
        $engine = is_array($crawl->progress_json['engine'] ?? null) ? $crawl->progress_json['engine'] : [];
        $queue = array_values(array_map('strval', is_array($engine['queue'] ?? null) ? $engine['queue'] : []));
        $index = max(0, (int) ($engine['index'] ?? 0));
        $seenList = is_array($engine['seen'] ?? null) ? $engine['seen'] : [];
        $seen = [];
        foreach ($seenList as $u) {
            $seen[(string) $u] = true;
        }
        // на всякий случай — всё из queue уже «seen»
        foreach ($queue as $u) {
            $seen[$u] = true;
        }

        return [
            'queue' => $queue,
            'index' => $index,
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
        array $seen,
        int $unchanged,
        int $expanded
    ): void {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['engine'] = [
            'queue' => array_values($queue),
            'index' => $index,
            'seen' => array_keys($seen),
            'unchanged' => $unchanged,
            'expanded' => $expanded,
        ];
        $crawl->progress_json = $progress;
    }

    private function clearEngineState(SiteAuditCrawl $crawl): void
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine']);
        $crawl->progress_json = $progress;
    }
}
