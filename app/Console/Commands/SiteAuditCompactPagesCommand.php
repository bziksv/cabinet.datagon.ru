<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditPageCompactor;
use App\Services\SiteAudit\SiteAuditPruner;
use App\SiteAuditCrawl;
use Illuminate\Console\Command;

class SiteAuditCompactPagesCommand extends Command
{
    protected $signature = 'site-audit:compact-pages
                            {--crawl= : Один crawl_id}
                            {--all-done : Все завершённые краулы с тяжёлым JSON}
                            {--delete-cancelled : Удалить cancelled-краулы целиком (pages+findings)}
                            {--chunk=2000 : Размер пачки UPDATE}
                            {--dry-run : Только список crawl_id}';

    protected $description = 'После агрегации обнуляет heavy JSON в site_audit_pages (ссылки/шинглы/img)';

    public function handle(): int
    {
        $compactor = new SiteAuditPageCompactor();
        $chunk = (int) $this->option('chunk');
        $dry = (bool) $this->option('dry-run');
        $crawlOpt = $this->option('crawl');

        if ($this->option('delete-cancelled') && ! $dry) {
            $pruner = new SiteAuditPruner();
            $cancelled = SiteAuditCrawl::query()
                ->where('status', SiteAuditCrawl::STATUS_CANCELLED)
                ->orderBy('id')
                ->get();
            $deleted = 0;
            foreach ($cancelled as $crawl) {
                $pruner->deleteCrawl($crawl);
                $deleted++;
                $this->line("deleted cancelled crawl #{$crawl->id}");
            }
            $this->info("cancelled deleted={$deleted}");
        } elseif ($this->option('delete-cancelled') && $dry) {
            $n = SiteAuditCrawl::query()->where('status', SiteAuditCrawl::STATUS_CANCELLED)->count();
            $this->info("dry-run: would delete cancelled crawls={$n}");
        }

        $onlyId = ($crawlOpt !== null && $crawlOpt !== '') ? (int) $crawlOpt : null;
        if ($onlyId === null && ! $this->option('all-done') && ! $this->option('delete-cancelled')) {
            $this->error('Укажите --crawl=ID или --all-done (и опционально --delete-cancelled).');

            return 1;
        }

        if ($onlyId === null && ! $this->option('all-done')) {
            return 0;
        }

        $ids = $compactor->crawlIdsNeedingCompact($onlyId);
        $this->info('crawls to compact: ' . count($ids));

        if ($dry) {
            foreach (array_slice($ids, 0, 40) as $id) {
                $this->line('  #' . $id);
            }
            if (count($ids) > 40) {
                $this->line('  …');
            }

            return 0;
        }

        $totalRows = 0;
        foreach ($ids as $id) {
            $res = $compactor->compactCrawl($id, $chunk);
            $totalRows += (int) $res['updated'];
            $this->line(sprintf('crawl #%d updated=%d', $id, $res['updated']));
        }

        $this->info("done: crawls=" . count($ids) . " rows_touched={$totalRows}");
        $this->comment('InnoDB не отдаст место ОС без OPTIMIZE TABLE (нужен запас диска ≈ размеру таблицы).');

        return 0;
    }
}
