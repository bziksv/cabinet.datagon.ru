<?php

namespace App\Support;

use App\MonitoringChangesDate;
use App\MonitoringSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Retention отчётов «Динамика конкурентов» (monitoring_changes_date).
 */
class MonitoringCompetitorsDynamicsRetention
{
    public const SETTING_KEY = 'competitors_changes_dates_retention_days';

    public const BATCH_SIZE = 200;

    public static function retentionDays(): int
    {
        $fromDb = MonitoringSettings::getValue(self::SETTING_KEY);
        if ($fromDb !== false && $fromDb !== null && $fromDb !== '') {
            return max(0, (int) $fromDb);
        }

        return max(0, (int) config('cabinet-monitoring.competitors_changes_dates_retention_days', 180));
    }

    /**
     * @return array{deleted: int, retention_days: int, skipped: bool, dry_run: bool}
     */
    public static function prune(?int $days = null, bool $dryRun = false): array
    {
        $days = $days ?? self::retentionDays();
        if ($days <= 0) {
            return [
                'deleted' => 0,
                'retention_days' => 0,
                'skipped' => true,
                'dry_run' => $dryRun,
            ];
        }

        $cutoff = Carbon::now()->subDays($days);
        $query = MonitoringChangesDate::query()
            ->whereIn('state', ['ready', 'fail'])
            ->where('created_at', '<', $cutoff);

        if ($dryRun) {
            return [
                'deleted' => (int) $query->count(),
                'retention_days' => $days,
                'skipped' => false,
                'dry_run' => true,
            ];
        }

        $deleted = 0;
        while (true) {
            $ids = (clone $query)->orderBy('id')->limit(self::BATCH_SIZE)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $count = MonitoringChangesDate::query()->whereIn('id', $ids->all())->delete();
            $deleted += $count;
        }

        if ($deleted > 0) {
            Log::info('monitoring competitors dynamics reports pruned', [
                'deleted' => $deleted,
                'retention_days' => $days,
                'cutoff' => $cutoff->toDateTimeString(),
            ]);
        }

        return [
            'deleted' => $deleted,
            'retention_days' => $days,
            'skipped' => false,
            'dry_run' => false,
        ];
    }
}
