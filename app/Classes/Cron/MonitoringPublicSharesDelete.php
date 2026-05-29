<?php

namespace App\Classes\Cron;

use App\MonitoringPublicShare;
use Carbon\Carbon;

class MonitoringPublicSharesDelete
{
    public function __invoke(): void
    {
        if (!MonitoringPublicShare::tableAvailable()) {
            return;
        }

        MonitoringPublicShare::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }
}
