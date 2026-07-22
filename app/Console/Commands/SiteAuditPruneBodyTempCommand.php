<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditBodyTemp;
use Illuminate\Console\Command;

class SiteAuditPruneBodyTempCommand extends Command
{
    protected $signature = 'site-audit:prune-body-tmp
                            {--force : Удалить все sa-body-* файлы}
                            {--crawl= : Только файлы одного crawl_id}';

    protected $description = 'Чистит временные HTML body Site Audit (TTL / caps / orphan)';

    public function handle(): int
    {
        $crawl = $this->option('crawl');
        $force = (bool) $this->option('force');
        $stats = SiteAuditBodyTemp::prune(
            $crawl !== null && $crawl !== '' ? (int) $crawl : null,
            $force
        );
        $this->info(sprintf(
            'body-tmp: deleted=%d kept=%d bytes=%d dir=%s',
            $stats['deleted'],
            $stats['kept'],
            $stats['bytes'],
            SiteAuditBodyTemp::dir()
        ));

        return 0;
    }
}
