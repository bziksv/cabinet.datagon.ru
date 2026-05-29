<?php

namespace App\Classes\Monitoring;

use App\MonitoringDataTableColumnsProject;
use App\MonitoringPosition;
use App\MonitoringProject;
use App\MonitoringSearchengine;
use App\Support\MonitoringProjectPublicStats;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * KPI на /monitoring/{id}: актуальные цифры (не застрявший кэш 2024 при графиках 2026).
 */
class MonitoringProjectPageSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function build(MonitoringProject $project, ?int $regionId = null): array
    {
        apply_team_permissions($project->id);

        try {
            if ($regionId !== null && $regionId > 0) {
                return self::liveForRegion($project, $regionId);
            }

            self::refreshSnapshotIfPositionsNewer($project);

            return MonitoringProjectPublicStats::buildForExport($project)['summary'];
        } finally {
            apply_global_team_permissions();
        }
    }

    private static function refreshSnapshotIfPositionsNewer(MonitoringProject $project): void
    {
        $engineIds = $project->searchengines()->pluck('id');
        if ($engineIds->isEmpty()) {
            return;
        }

        $latestAt = MonitoringPosition::query()
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->max('updated_at');

        if ($latestAt === null) {
            return;
        }

        $latest = Carbon::parse($latestAt);

        /** @var MonitoringDataTableColumnsProject|null $snap */
        $snap = MonitoringDataTableColumnsProject::query()
            ->where('monitoring_project_id', $project->id)
            ->first();

        if ($snap !== null && $snap->updated_at !== null && $snap->updated_at->gte($latest)) {
            return;
        }

        app(MonitoringProjectSnapshotService::class)->refreshProject($project);
    }

    /**
     * KPI по последним позициям выбранного региона (как график % в ТОП).
     *
     * @return array<string, mixed>
     */
    private static function liveForRegion(MonitoringProject $project, int $regionId): array
    {
        /** @var MonitoringSearchengine|null $engine */
        $engine = $project->searchengines()->where('id', $regionId)->first();
        if ($engine === null) {
            return MonitoringProjectPublicStats::buildForExport($project)['summary'];
        }

        $positions = self::latestPositionsForEngine($project, $engine);
        $calc = new PositionsPercentCalculate($positions);

        $latestAt = MonitoringPosition::query()
            ->where('monitoring_searchengine_id', $engine->id)
            ->max('updated_at');

        $words = (int) $project->keywords()->count();

        return [
            'words' => $words,
            'middle' => $calc->middle(),
            'top1' => $calc->top1(),
            'diff_top1' => null,
            'top3' => $calc->top3(),
            'diff_top3' => null,
            'top5' => $calc->top5(),
            'diff_top5' => null,
            'top10' => $calc->top10(),
            'diff_top10' => null,
            'top30' => $calc->top30(),
            'diff_top30' => null,
            'top100' => $calc->top100(),
            'diff_top100' => null,
            'mastered' => null,
            'mastered_percent' => null,
            'snapshot_at' => $latestAt
                ? Carbon::parse($latestAt)->format('d.m.Y H:i')
                : null,
            'snapshot_scope' => 'region',
            'has_data' => $positions->isNotEmpty(),
        ];
    }

    private static function latestPositionsForEngine(MonitoringProject $project, MonitoringSearchengine $engine): Collection
    {
        $sep = ProjectDependencies::POSITION_SEPARATOR;
        $prefix = ProjectDependencies::POSITION_PREFIX;
        $postfix = ProjectDependencies::POSITION_POSTFIX;
        $field = implode($sep, [$prefix, $engine->id, $postfix]);

        $queries = $project->keywords()
            ->addLastPositions($sep, $prefix, $postfix, collect([$engine->id]))
            ->get();

        return $queries->map(function ($row) use ($field) {
            return $row->{$field} ?? null;
        })->filter(function ($pos) {
            return $pos !== null && $pos !== '';
        })->values();
    }
}
