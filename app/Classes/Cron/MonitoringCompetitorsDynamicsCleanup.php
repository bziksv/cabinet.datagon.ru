<?php

namespace App\Classes\Cron;

use App\Support\MonitoringCompetitorsDynamicsRetention;

class MonitoringCompetitorsDynamicsCleanup
{
    public function __invoke(): void
    {
        MonitoringCompetitorsDynamicsRetention::prune();
    }
}
