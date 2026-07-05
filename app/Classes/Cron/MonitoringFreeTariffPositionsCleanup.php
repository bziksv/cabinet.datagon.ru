<?php

namespace App\Classes\Cron;

use App\Support\MonitoringFreeTariffRetention;

class MonitoringFreeTariffPositionsCleanup
{
    public function __invoke(): void
    {
        MonitoringFreeTariffRetention::prunePositions();
    }
}
