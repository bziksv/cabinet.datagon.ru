<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditPruner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Фоновая зачистка pages/findings после удаления crawl из UI.
 * Один DELETE на сотни тысяч findings в HTTP висит минутами и лочит таблицу.
 */
class DeleteSiteAuditCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 3600;

    /** @var int */
    public $crawlId;

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        // default: много воркеров; site_audit занят краулом, db-optimize — OPTIMIZE.
        $this->onQueue((string) config('site_audit.delete_queue', 'default'));
    }

    public function handle(): void
    {
        try {
            (new SiteAuditPruner())->purgeCrawlRows((int) $this->crawlId);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit DeleteSiteAuditCrawlJob failed: ' . $e->getMessage(), [
                'crawl_id' => $this->crawlId,
            ]);
            throw $e;
        }
    }
}
