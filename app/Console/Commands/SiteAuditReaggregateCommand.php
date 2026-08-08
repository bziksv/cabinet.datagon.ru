<?php

namespace App\Console\Commands;

use App\Jobs\SiteAudit\AggregateSiteAuditCrawlJob;
use App\Services\SiteAudit\SiteAuditAggregator;
use App\SiteAuditCrawl;
use Illuminate\Console\Command;

class SiteAuditReaggregateCommand extends Command
{
    protected $signature = 'site-audit:reaggregate
        {crawl_id : ID краула}
        {--notify : Отправить email о завершении}
        {--queue : В очередь тиками (для больших краулов, не блокирует CLI)}';

    protected $description = 'Пересчитать aggregate-findings по уже скачанным pages';

    public function handle(): int
    {
        $id = (int) $this->argument('crawl_id');
        $crawl = SiteAuditCrawl::query()->find($id);
        if (! $crawl) {
            $this->error('Crawl not found');

            return 1;
        }

        $notify = (bool) $this->option('notify');
        $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
        $crawl->error = null;
        $crawl->finished_at = null;
        $crawl->save();

        if ($this->option('queue')) {
            (new SiteAuditAggregator())->resetAggregateState($crawl, $notify);
            AggregateSiteAuditCrawlJob::dispatch($crawl->id);
            $this->info("Queued staged aggregate for crawl #{$id}");

            return 0;
        }

        (new SiteAuditAggregator())->aggregate($crawl, $notify);
        $crawl->refresh();

        $this->info('Status: ' . $crawl->statusLabelRu());
        $this->info('Buckets: ' . json_encode($crawl->buckets_json, JSON_UNESCAPED_UNICODE));
        $this->info('Counts: ' . json_encode($crawl->counts_json, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
