<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditChecklistSyncService;
use App\SiteAuditCrawl;
use Illuminate\Console\Command;

class SiteAuditSyncChecklistCommand extends Command
{
    protected $signature = 'site-audit:sync-checklist {crawl_id : ID завершённого краула} {--dry : Только показать план без записи} {--force : Синхронизировать даже если связка выключена}';

    protected $description = 'Синхронизирует проблемы завершённого Site Audit в привязанный SEO-чеклист';

    public function handle(): int
    {
        $crawlId = (int) $this->argument('crawl_id');
        $crawl = SiteAuditCrawl::query()->find($crawlId);
        if (! $crawl) {
            $this->error('Краул #' . $crawlId . ' не найден.');

            return 1;
        }

        $res = (new SiteAuditChecklistSyncService())->syncCrawl(
            $crawl,
            (bool) $this->option('dry'),
            (bool) $this->option('force')
        );

        if (! empty($res['skipped'])) {
            $this->warn('Пропущено: ' . ($res['reason'] ?? 'unknown'));

            return 0;
        }

        $mode = ! empty($res['dry']) ? ' [DRY-RUN]' : '';
        $this->info(sprintf(
            'Краул #%d -> чеклист #%d%s: создано %d, обновлено %d, на проверку %d, регрессий %d, игнор %d',
            $res['crawl_id'],
            $res['checklist_project_id'] ?? 0,
            $mode,
            $res['created'],
            $res['updated'],
            $res['moved_to_review'],
            $res['reopened'],
            $res['skipped_ignored']
        ));

        foreach ($res['actions'] as $a) {
            $this->line(sprintf(
                '  [%s] %s (%s) -> %s',
                $a['action'],
                $a['code'],
                $a['stage_key'] ?? '-',
                $a['title'] ?? ''
            ));
        }

        return 0;
    }
}
