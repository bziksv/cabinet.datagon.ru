<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditCrawlEngine;
use App\SiteAuditCrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Продолжение краула после батча (обход URL порциями, чтобы не упираться в timeout воркера).
 */
class ContinueSiteAuditCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    /** Секунд: батч ~4 мин + запас на discover-хвост / сеть */
    public $timeout = 900;

    /** @var int */
    public $crawlId;

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        $this->onQueue(config('site_audit.queue', 'site_audit'));
    }

    public function handle(): void
    {
        $crawl = SiteAuditCrawl::query()->find($this->crawlId);
        if (! $crawl || $crawl->isFinished()) {
            return;
        }
        if ($crawl->status === SiteAuditCrawl::STATUS_QUEUED_WAIT) {
            return;
        }
        if ($crawl->status === SiteAuditCrawl::STATUS_AGGREGATING) {
            return;
        }

        (new SiteAuditCrawlEngine())->processBatch($crawl, true);
    }
}
