<?php

namespace App\Console\Commands;

use App\Classes\Monitoring\MonitoringProjectSnapshotService;
use App\MonitoringProject;
use Illuminate\Console\Command;

class MonitoringRefreshSnapshots extends Command
{
    protected $signature = 'monitoring:refresh-snapshots
                            {--stale= : пересчитать снимки старше N часов}
                            {--project= : id одного проекта}
                            {--all : все проекты}';

    protected $description = 'Пересчитать monitoring_data_table_columns_projects (ТОП, позиция, слова для списка)';

    public function handle(MonitoringProjectSnapshotService $service): int
    {
        if ($projectId = $this->option('project')) {
            $service->refreshProjectId((int) $projectId);
            $this->info('Project #' . $projectId . ' done.');

            return 0;
        }

        if ($this->option('all')) {
            $n = 0;
            MonitoringProject::query()->orderBy('id')->chunk(25, function ($chunk) use ($service, &$n) {
                $n += $service->refreshMany($chunk);
            });
            $this->info("Refreshed {$n} projects.");

            return 0;
        }

        $hours = $this->option('stale');
        $n = $service->refreshStale($hours !== null ? (int) $hours : 24);
        $this->info("Refreshed {$n} stale projects.");

        return 0;
    }
}
