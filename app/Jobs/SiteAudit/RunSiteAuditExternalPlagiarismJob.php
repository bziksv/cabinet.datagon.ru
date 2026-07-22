<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditExternalPlagiarismRunner;
use App\SiteAuditCrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSiteAuditExternalPlagiarismJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /** SERP-зонды на несколько URL — дольше обычного aggregate. */
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
        if (! $crawl) {
            return;
        }

        try {
            (new SiteAuditExternalPlagiarismRunner())->run($crawl);
        } catch (\Throwable $e) {
            $crawl->refresh();
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $state = is_array($progress[SiteAuditExternalPlagiarismRunner::PROGRESS_KEY] ?? null)
                ? $progress[SiteAuditExternalPlagiarismRunner::PROGRESS_KEY]
                : [];
            $state['status'] = 'failed';
            $state['error'] = mb_substr($e->getMessage(), 0, 500);
            $state['finished_at'] = now()->toDateTimeString();
            $progress[SiteAuditExternalPlagiarismRunner::PROGRESS_KEY] = $state;
            $crawl->progress_json = $progress;
            $crawl->save();
            throw $e;
        }
    }
}
