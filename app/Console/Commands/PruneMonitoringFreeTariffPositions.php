<?php

namespace App\Console\Commands;

use App\Support\MonitoringFreeTariffRetention;
use Illuminate\Console\Command;

class PruneMonitoringFreeTariffPositions extends Command
{
    protected $signature = 'monitoring:prune-free-positions
                            {--days= : Override retention days}
                            {--dry-run : Count rows without deleting}';

    protected $description = 'Delete monitoring_positions older than N days for projects owned by Free tariff users';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = MonitoringFreeTariffRetention::prunePositions($days, $dryRun);

        if ($result['skipped']) {
            $this->warn('Retention is disabled (days = 0). Set free_tariff_positions_retention_days in /monitoring/admin.');

            return 0;
        }

        $label = $dryRun ? 'Would delete' : 'Deleted';
        $this->info(sprintf(
            '%s %d position row(s) across %d project(s); retention %d days.',
            $label,
            $result['deleted'],
            $result['projects'],
            $result['retention_days']
        ));

        return 0;
    }
}
