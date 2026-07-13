<?php

namespace App\Classes\Monitoring;

use App\MonitoringDataTableColumnsProject;
use App\MonitoringProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Снимок метрик списка проектов (ТОП, слова, освоено) — таблица monitoring_data_table_columns_projects.
 *
 * Список в UI читает снимок; пересчёт — фоном (cron / после снятия позиций), не на каждый HTTP.
 */
class MonitoringProjectSnapshotService
{
    public function refreshProject(MonitoringProject $project): void
    {
        apply_team_permissions($project->id);

        try {
            $pd = new ProjectData($project);
            $pd->save();
            $this->forgetListCacheForProjectUsers($project);
            MonitoringChildRowsService::forgetProjectCache((int) $project->id);
        } catch (\Throwable $e) {
            Log::warning('monitoring snapshot refresh failed', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            apply_global_team_permissions();
        }
    }

    private function forgetListCacheForProjectUsers(MonitoringProject $project): void
    {
        $project->loadMissing('users:id');
        foreach ($project->users as $user) {
            MonitoringProjectListSerializer::forgetCacheForUser((int) $user->id);
        }
    }

    public function refreshProjectId(int $projectId): void
    {
        $project = MonitoringProject::query()->with('users')->find($projectId);

        if ($project) {
            $this->refreshProject($project);
        }
    }

    /**
     * @param iterable<MonitoringProject|int> $projects
     */
    public function refreshMany(iterable $projects): int
    {
        $count = 0;

        foreach ($projects as $project) {
            if (is_numeric($project)) {
                $project = MonitoringProject::query()->with('users')->find((int) $project);
            }

            if (!$project instanceof MonitoringProject) {
                continue;
            }

            $this->refreshProject($project);
            $count++;
        }

        return $count;
    }

    /**
     * Проекты без снимка или со устаревшим снимком (по updated_at строки кэша).
     */
    public function refreshStale(?int $olderThanHours = 24, int $chunkSize = 25): int
    {
        $threshold = now()->subHours($olderThanHours ?? 24);
        $updated = 0;

        MonitoringProject::query()
            ->orderBy('id')
            ->chunk($chunkSize, function ($chunk) use ($threshold, &$updated) {
                foreach ($chunk as $project) {
                    $cached = MonitoringDataTableColumnsProject::query()
                        ->where('monitoring_project_id', $project->id)
                        ->first();

                    if ($cached && $cached->updated_at && $cached->updated_at->gte($threshold)) {
                        continue;
                    }

                    try {
                        $this->refreshProject($project);
                        $updated++;
                    } catch (\Throwable $e) {
                        Log::warning('monitoring snapshot refresh failed', [
                            'project_id' => $project->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $updated;
    }

    /**
     * @return Collection<int, MonitoringDataTableColumnsProject>
     */
    public function cachedByProjectIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return MonitoringDataTableColumnsProject::query()
            ->whereIn('monitoring_project_id', $ids)
            ->get()
            ->keyBy('monitoring_project_id');
    }
}
