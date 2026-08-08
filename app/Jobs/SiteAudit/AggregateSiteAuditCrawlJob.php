<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditAggregator;
use App\SiteAuditCrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Агрегация краула тиками: один job = несколько лёгких этапов или кусок тяжёлого.
 * Большие сайты (десятки тысяч URL) дожимаются цепочкой Continue без 300s timeout.
 */
class AggregateSiteAuditCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $timeout = 600;

    /** @var int */
    public $crawlId;

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        $this->onQueue(config('site_audit.queue', 'site_audit'));
        $this->timeout = max(300, (int) config('site_audit.aggregate_job_timeout', 600));
    }

    public function handle(): void
    {
        $lockKey = 'site_audit_aggregate_' . $this->crawlId;
        $lockTtl = max(
            180,
            (int) config('site_audit.aggregate_tick_seconds', 150) + 180
        );
        if (! Cache::add($lockKey, 1, $lockTtl)) {
            self::dispatch($this->crawlId)->delay(now()->addSeconds(20));

            return;
        }

        try {
            $crawl = SiteAuditCrawl::query()->find($this->crawlId);
            if (! $crawl || $crawl->isFinished()) {
                return;
            }

            if ($crawl->status !== SiteAuditCrawl::STATUS_AGGREGATING) {
                $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
                $crawl->error = null;
                $crawl->save();
            }

            $more = (new SiteAuditAggregator())->processTick($crawl, true);
            if ($more) {
                $pause = max(1, (int) config('site_audit.aggregate_tick_pause_seconds', 3));
                self::dispatch($this->crawlId)->delay(now()->addSeconds($pause));
            }
        } catch (\Throwable $e) {
            $crawl = SiteAuditCrawl::query()->find($this->crawlId);
            if ($crawl && ! $crawl->isFinished()) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Aggregate failed: ' . mb_substr($e->getMessage(), 0, 500);
                $crawl->finished_at = now();
                $crawl->save();
                \App\Services\SiteAudit\SiteAuditGlobalCap::promoteWaiting();
            }
            Log::error('SiteAudit aggregate tick failed', [
                'crawl_id' => $this->crawlId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            Cache::forget($lockKey);
        }
    }
}
