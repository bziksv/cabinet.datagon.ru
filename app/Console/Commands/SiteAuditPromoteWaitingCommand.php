<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditGlobalCap;
use Illuminate\Console\Command;

class SiteAuditPromoteWaitingCommand extends Command
{
    protected $signature = 'site-audit:promote-waiting';

    protected $description = 'Снимает stale, оживляет оборванные краулы, запускает queued_wait';

    public function handle(): int
    {
        $reclaimed = SiteAuditGlobalCap::reclaimStale();
        $kicked = SiteAuditGlobalCap::kickStuckActive();
        $n = SiteAuditGlobalCap::promoteWaiting();
        $this->info(sprintf(
            'reclaimed=%d kicked=%d promoted=%d active=%d max=%d',
            $reclaimed,
            $kicked,
            $n,
            SiteAuditGlobalCap::countActive(),
            SiteAuditGlobalCap::maxActive()
        ));

        return 0;
    }
}
