<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditGlobalCap;
use Illuminate\Console\Command;

class SiteAuditPromoteWaitingCommand extends Command
{
    protected $signature = 'site-audit:promote-waiting';

    protected $description = 'Запускает краулы из глобальной очереди queued_wait при свободном слоте';

    public function handle(): int
    {
        $n = SiteAuditGlobalCap::promoteWaiting();
        $this->info(sprintf(
            'promoted=%d active=%d max=%d',
            $n,
            SiteAuditGlobalCap::countActive(),
            SiteAuditGlobalCap::maxActive()
        ));

        return 0;
    }
}
