<?php

namespace App\Console\Commands;

use App\Support\MonitoringCompetitorsDynamicsRetention;
use Illuminate\Console\Command;

class PruneMonitoringCompetitorsDynamics extends Command
{
    protected $signature = 'monitoring:prune-competitors-dynamics
                            {--days= : Override retention days}
                            {--dry-run : Count rows without deleting}';

    protected $description = 'Delete ready/fail competitors dynamics reports (monitoring_changes_date) older than N days';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = MonitoringCompetitorsDynamicsRetention::prune($days, $dryRun);

        if ($result['skipped']) {
            $this->warn('Retention is disabled (days = 0). Set competitors_changes_dates_retention_days in /monitoring/admin.');

            return 0;
        }

        $label = $dryRun ? 'Would delete' : 'Deleted';
        $this->info(sprintf(
            '%s %d report row(s); retention %d days.',
            $label,
            $result['deleted'],
            $result['retention_days']
        ));

        return 0;
    }
}
