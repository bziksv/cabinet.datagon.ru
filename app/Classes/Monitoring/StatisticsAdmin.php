<?php


namespace App\Classes\Monitoring;


use App\Jobs;
use App\MonitoringProject;
use App\MonitoringStat;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StatisticsAdmin
{
    private const DASHBOARD_CACHE_KEY = 'monitoring_admin_dashboard_stats_v2';

    private const DASHBOARD_CACHE_SECONDS = 300;
    protected $projects;
    protected $jobs;
    protected $stat;

    public function __construct()
    {
        $this->projects = new MonitoringProject();
        $this->jobs = new Jobs();
        $this->stat = new MonitoringStat();
    }

    public function getCountOfCheckUpForCurrentDay()
    {
        $name = __('Count of check up for current day');
        $val = $this->stat->currentDay()->count();

        if(!$val)
            return null;

        return collect(['name' => $name, 'val' => $val]);
    }

    public function getCountOfCheckUpForCurrentMonth()
    {
        $name = __('Count of check up for current month');
        $val = $this->stat->currentMonth()->count();

        if(!$val)
            return null;

        return collect(['name' => $name, 'val' => $val]);
    }

    public function getCountOfErrorsForCurrentDay()
    {
        $name = __('Count of errors for current day');
        $val = $this->stat->withErrors()->currentDay()->count();

        if(!$val)
            return null;

        return collect(['name' => $name, 'val' => $val]);
    }

    public function getCountOfProjects()
    {
        $name = __('Count of projects');
        $val = $this->projects->count();

        if(!$val)
            return null;

        return collect(['name' => $name, 'val' => $val]);
    }

    public function getCountOfTasksInQueue()
    {
        $name = __('Count of tasks in queue');
        $val = $this->jobs->positionsQueue()->count();

        if(!$val)
            return null;

        return collect(['name' => $name, 'val' => $val]);
    }

    /**
     * Виджеты для /monitoring/stat — один проход по monitoring_stats + кэш 5 мин.
     */
    public function getDashboardStatistics(): Collection
    {
        return Cache::remember(self::DASHBOARD_CACHE_KEY, self::DASHBOARD_CACHE_SECONDS, function () {
            $startOfDay = Carbon::now()->startOfDay()->toDateTimeString();
            $startOfMonth = Carbon::now()->startOfMonth()->toDateTimeString();

            $aggregates = MonitoringStat::query()
                ->selectRaw(
                    'SUM(CASE WHEN created_at > ? THEN 1 ELSE 0 END) as day_count,
                     SUM(CASE WHEN created_at > ? THEN 1 ELSE 0 END) as month_count,
                     SUM(CASE WHEN created_at > ? AND errors = 1 THEN 1 ELSE 0 END) as day_errors_count',
                    [$startOfDay, $startOfMonth, $startOfDay]
                )
                ->first();

            $dayCount = (int) ($aggregates->day_count ?? 0);
            $monthCount = (int) ($aggregates->month_count ?? 0);
            $dayErrors = (int) ($aggregates->day_errors_count ?? 0);
            $projectsCount = $this->projects->count();
            $queueCount = $this->jobs->positionsQueue()->count();

            $rows = collect([
                $this->statRow(__('Count of check up for current day'), $dayCount),
                $this->statRow(__('Count of check up for current month'), $monthCount),
                $this->statRow(__('Count of errors for current day'), $dayErrors),
                $this->statRow(__('Count of projects'), $projectsCount),
                $this->statRow(__('Count of tasks in queue'), $queueCount),
            ]);

            return $rows->filter()->values();
        });
    }

    protected function statRow(string $name, int $value): ?Collection
    {
        if ($value <= 0) {
            return null;
        }

        return collect(['name' => $name, 'val' => $value]);
    }

}
