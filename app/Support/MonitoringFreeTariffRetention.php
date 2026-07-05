<?php

namespace App\Support;

use App\Classes\Monitoring\MonitoringProjectSnapshotService;
use App\MonitoringSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retention monitoring_positions для владельцев Free (по creator проекта).
 */
class MonitoringFreeTariffRetention
{
    public const SETTING_KEY = 'free_tariff_positions_retention_days';

    public const BATCH_SIZE = 5000;

    public static function retentionDays(): int
    {
        $fromDb = MonitoringSettings::getValue(self::SETTING_KEY);
        if ($fromDb !== false && $fromDb !== null && $fromDb !== '') {
            return max(0, (int) $fromDb);
        }

        return max(0, (int) config('cabinet-monitoring.free_tariff_positions_retention_days', 365));
    }

    /**
     * @return array{deleted: int, projects: int, retention_days: int, skipped: bool, dry_run: bool}
     */
    public static function prunePositions(?int $days = null, bool $dryRun = false): array
    {
        $days = $days ?? self::retentionDays();
        if ($days <= 0) {
            return [
                'deleted' => 0,
                'projects' => 0,
                'retention_days' => 0,
                'skipped' => true,
                'dry_run' => $dryRun,
            ];
        }

        $cutoff = Carbon::now()->subDays($days);
        $userIds = MonitoringPositionsSchedule::freeTariffUserIds();

        if ($userIds->isEmpty()) {
            return [
                'deleted' => 0,
                'projects' => 0,
                'retention_days' => $days,
                'skipped' => false,
                'dry_run' => $dryRun,
            ];
        }

        if ($dryRun) {
            $count = (int) self::positionsQuery($userIds, $cutoff)->count();
            $projects = (int) self::positionsQuery($userIds, $cutoff)
                ->distinct()
                ->count('mproj.id');

            return [
                'deleted' => $count,
                'projects' => $projects,
                'retention_days' => $days,
                'skipped' => false,
                'dry_run' => true,
            ];
        }

        $deleted = 0;
        $affectedProjectIds = [];

        while (true) {
            $rows = self::positionsQuery($userIds, $cutoff)
                ->select(['mp.id', 'mproj.id as project_id'])
                ->limit(self::BATCH_SIZE)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $ids = $rows->pluck('id')->all();
            DB::table('monitoring_positions')->whereIn('id', $ids)->delete();
            $deleted += count($ids);

            foreach ($rows as $row) {
                $affectedProjectIds[(int) $row->project_id] = true;
            }
        }

        $projectCount = count($affectedProjectIds);
        if ($projectCount > 0) {
            try {
                $snapshot = app(MonitoringProjectSnapshotService::class);
                $snapshot->refreshMany(array_keys($affectedProjectIds));
            } catch (\Throwable $e) {
                Log::warning('monitoring free retention snapshot refresh failed', [
                    'message' => $e->getMessage(),
                    'projects' => $projectCount,
                ]);
            }
        }

        Log::info('monitoring free tariff positions pruned', [
            'deleted' => $deleted,
            'projects' => $projectCount,
            'retention_days' => $days,
            'cutoff' => $cutoff->toDateTimeString(),
        ]);

        return [
            'deleted' => $deleted,
            'projects' => $projectCount,
            'retention_days' => $days,
            'skipped' => false,
            'dry_run' => false,
        ];
    }

    /**
     * @param Collection<int, int>|array<int, int> $userIds
     */
    private static function positionsQuery($userIds, Carbon $cutoff)
    {
        return DB::table('monitoring_positions as mp')
            ->join('monitoring_keywords as mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
            ->join('monitoring_projects as mproj', 'mproj.id', '=', 'mk.monitoring_project_id')
            ->whereIn('mproj.creator', collect($userIds)->values()->all())
            ->where('mp.created_at', '<', $cutoff);
    }
}
