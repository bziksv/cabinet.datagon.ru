<?php

namespace App\Services\SiteAudit;

use App\Jobs\SiteAudit\DiscoverSiteAuditUrlsJob;
use App\SiteAuditCrawl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Глобальный backpressure: одновременно N краулов на весь сервер.
 * Остальные — queued_wait без Discover/Fetch jobs.
 * Cabinet default N=2 (как numprocs site_audit); proxy2 — SITE_AUDIT_GLOBAL_MAX_ACTIVE.
 */
class SiteAuditGlobalCap
{
    public const LOCK_KEY = 'site_audit_global_cap_lock';

    public static function activeStatuses(): array
    {
        return [
            SiteAuditCrawl::STATUS_QUEUED,
            SiteAuditCrawl::STATUS_DISCOVERING,
            SiteAuditCrawl::STATUS_FETCHING,
            SiteAuditCrawl::STATUS_AGGREGATING,
        ];
    }

    public static function maxActive(): int
    {
        return max(1, (int) config('site_audit.global_max_active_crawls', 1));
    }

    public static function countActive(?int $exceptCrawlId = null): int
    {
        $q = SiteAuditCrawl::query()->whereIn('status', self::activeStatuses());
        if ($exceptCrawlId !== null) {
            $q->where('id', '!=', $exceptCrawlId);
        }

        return (int) $q->count();
    }

    /** Есть ли у пользователя другой краул в работе (не queued_wait). */
    public static function userHasOtherActive(int $userId, ?int $exceptCrawlId = null): bool
    {
        if ($userId < 1) {
            return false;
        }
        $q = SiteAuditCrawl::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::activeStatuses());
        if ($exceptCrawlId !== null) {
            $q->where('id', '!=', $exceptCrawlId);
        }

        return $q->exists();
    }

    /**
     * Короткое описание, что блокирует слот у пользователя (для UI).
     */
    public static function blockingActiveSummary(int $userId, ?int $exceptCrawlId = null): ?string
    {
        if ($userId < 1) {
            return null;
        }
        $q = SiteAuditCrawl::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::activeStatuses())
            ->with('project:id,domain')
            ->orderBy('id');
        if ($exceptCrawlId !== null) {
            $q->where('id', '!=', $exceptCrawlId);
        }
        $crawl = $q->first();
        if (! $crawl) {
            return null;
        }
        $domain = optional($crawl->project)->domain ?: 'проект';

        return '#' . $crawl->id . ' · ' . $domain . ' · ' . $crawl->statusLabelRu();
    }

    /**
     * Зависшие active-краулы (нет updated_at дольше N мин) → failed, иначе слот вечный.
     */
    public static function reclaimStale(): int
    {
        $minutes = max(15, (int) config('site_audit.stale_active_minutes', 120));
        $cutoff = now()->subMinutes($minutes);
        $stale = SiteAuditCrawl::query()
            ->whereIn('status', self::activeStatuses())
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $n = 0;
        foreach ($stale as $crawl) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'Прерван: нет прогресса более ' . $minutes . ' мин (освобождение слота)';
            $crawl->finished_at = now();
            $crawl->save();
            $n++;
            Log::warning('SiteAudit stale crawl reclaimed', [
                'crawl_id' => $crawl->id,
                'minutes' => $minutes,
            ]);
        }

        return $n;
    }

    /**
     * Оживить краулы, у которых оборвалась цепочка job (lock/deadlock/restart),
     * до того как reclaimStale убьёт слот.
     */
    public static function kickStuckActive(): int
    {
        $idleMinutes = max(2, (int) config('site_audit.kick_idle_minutes', 5));
        $staleMinutes = max(15, (int) config('site_audit.stale_active_minutes', 120));
        $idleCutoff = now()->subMinutes($idleMinutes);
        // Не трогаем тех, кого уже пора reclaim'ить — их снимет reclaimStale.
        $staleCutoff = now()->subMinutes($staleMinutes);

        $stuck = SiteAuditCrawl::query()
            ->whereIn('status', [
                SiteAuditCrawl::STATUS_QUEUED,
                SiteAuditCrawl::STATUS_DISCOVERING,
                SiteAuditCrawl::STATUS_FETCHING,
            ])
            ->where('updated_at', '<', $idleCutoff)
            ->where('updated_at', '>=', $staleCutoff)
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($stuck->isEmpty()) {
            return 0;
        }

        $engine = new SiteAuditCrawlEngine();
        $n = 0;
        foreach ($stuck as $crawl) {
            $dedupe = 'site_audit_kick_' . $crawl->id;
            if (! Cache::add($dedupe, 1, max(60, $idleMinutes * 60))) {
                continue;
            }

            try {
                if ($crawl->status === SiteAuditCrawl::STATUS_FETCHING || $engine->hasEngineState($crawl)) {
                    $engine->pushContinueJob((int) $crawl->id);
                } else {
                    self::pushDiscoverJob((int) $crawl->id);
                }
                $n++;
                Log::warning('SiteAudit kicked stuck crawl', [
                    'crawl_id' => $crawl->id,
                    'status' => $crawl->status,
                    'updated_at' => (string) $crawl->updated_at,
                ]);
            } catch (\Throwable $e) {
                Log::warning('SiteAudit kick stuck failed: ' . $e->getMessage(), [
                    'crawl_id' => $crawl->id,
                ]);
            }
        }

        return $n;
    }

    private static function pushDiscoverJob(int $crawlId): void
    {
        $queue = (string) (config('site_audit.queue') ?: 'site_audit');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                Queue::pushOn($queue, new DiscoverSiteAuditUrlsJob($crawlId));

                return;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $deadlock = stripos($msg, 'Deadlock') !== false || strpos($msg, '1213') !== false;
                if (! $deadlock || $attempt >= 3) {
                    throw $e;
                }
                usleep(150000 * ($attempt + 1));
            }
        }
    }

    /**
     * @param callable $fn
     * @return mixed
     */
    private static function withLock(callable $fn)
    {
        $got = false;
        for ($i = 0; $i < 40; $i++) {
            if (Cache::add(self::LOCK_KEY, 1, 30)) {
                $got = true;
                break;
            }
            usleep(100000); // 100ms
        }
        if (! $got) {
            throw new \RuntimeException('SiteAudit global cap lock timeout');
        }
        try {
            return $fn();
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    public static function tryDispatch(SiteAuditCrawl $crawl): bool
    {
        if ($crawl->isFinished()) {
            return false;
        }

        try {
            return (bool) self::withLock(function () use ($crawl) {
                $crawl->refresh();
                if ($crawl->isFinished()) {
                    return false;
                }

                self::reclaimStale();

                // 1 активный краул на пользователя (остальные ждут в очереди)
                if (self::userHasOtherActive((int) $crawl->user_id, (int) $crawl->id)) {
                    if ($crawl->status !== SiteAuditCrawl::STATUS_QUEUED_WAIT) {
                        $crawl->status = SiteAuditCrawl::STATUS_QUEUED_WAIT;
                        $crawl->save();
                    }

                    return false;
                }

                if (self::countActive((int) $crawl->id) >= self::maxActive()) {
                    if ($crawl->status !== SiteAuditCrawl::STATUS_QUEUED_WAIT) {
                        $crawl->status = SiteAuditCrawl::STATUS_QUEUED_WAIT;
                        $crawl->save();
                    }

                    return false;
                }

                $crawl->status = SiteAuditCrawl::STATUS_QUEUED;
                if (! $crawl->started_at) {
                    $crawl->started_at = now();
                }
                $crawl->save();
                // Уже есть очередь на диске (resume / оборванный fetching) — только Continue.
                $engine = new SiteAuditCrawlEngine();
                if ($engine->hasEngineState($crawl) && (int) $crawl->pages_fetched > 0) {
                    $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
                    $crawl->save();
                    $engine->pushContinueJob((int) $crawl->id, 1);
                } else {
                    DiscoverSiteAuditUrlsJob::dispatch($crawl->id);
                }

                // Local queue: если воркер мёртв/в sandbox без MySQL — краул иначе вечно «Запуск».
                \App\Support\SiteAuditLocalQueueGuard::ensureWorkers();

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('SiteAudit global cap dispatch failed: ' . $e->getMessage(), [
                'crawl_id' => $crawl->id,
            ]);
            if (! $crawl->isFinished() && $crawl->status !== SiteAuditCrawl::STATUS_QUEUED_WAIT) {
                $crawl->status = SiteAuditCrawl::STATUS_QUEUED_WAIT;
                $crawl->save();
            }

            return false;
        }
    }

    public static function promoteWaiting(): int
    {
        try {
            return (int) self::withLock(function () {
                self::reclaimStale();
                self::kickStuckActive();

                $slots = self::maxActive() - self::countActive();
                if ($slots <= 0) {
                    return 0;
                }

                $waiting = SiteAuditCrawl::query()
                    ->where('status', SiteAuditCrawl::STATUS_QUEUED_WAIT)
                    ->orderBy('id')
                    ->limit(max(20, $slots * 5))
                    ->get();

                $started = 0;
                foreach ($waiting as $crawl) {
                    if ($started >= $slots) {
                        break;
                    }
                    if (self::userHasOtherActive((int) $crawl->user_id, (int) $crawl->id)) {
                        continue;
                    }
                    if (self::countActive((int) $crawl->id) >= self::maxActive()) {
                        break;
                    }
                    $crawl->status = SiteAuditCrawl::STATUS_QUEUED;
                    $crawl->started_at = $crawl->started_at ?: now();
                    $crawl->save();
                    DiscoverSiteAuditUrlsJob::dispatch($crawl->id);
                    $started++;
                }

                return $started;
            });
        } catch (\Throwable $e) {
            Log::warning('SiteAudit promoteWaiting failed: ' . $e->getMessage());

            return 0;
        }
    }
}
