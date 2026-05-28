<?php

namespace App\Classes\Monitoring;

use App\Http\Controllers\MonitoringController;
use App\MonitoringKeywordPrice;
use App\MonitoringPosition;
use App\MonitoringSearchengine;
use App\MonitoringProject;
use App\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Регионы и дни (child-rows): один запрос позиций вместо N×5, кэш HTML.
 */
class MonitoringChildRowsService
{
    private const CACHE_TTL_SECONDS = 600;

    /** Выше — грузим помесячно (5 запросов), иначе один запрос за 12 мес. */
    private const SINGLE_QUERY_MAX_ROWS = 120000;

    /** @var MonitoringController */
    private $metrics;

    public function __construct(MonitoringController $metrics)
    {
        $this->metrics = $metrics;
    }

    public function htmlForProject(User $user, int $projectId, $groupId = null): string
    {
        $project = $user->monitoringProjects()->findOrFail($projectId);
        $groupKey = $groupId ? (string) $groupId : '0';
        $cacheKey = $this->cacheKey($projectId, $groupKey);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($project, $groupId) {
            $groups = $this->buildGroups($project, $groupId);

            return view('monitoring.partials._child_rows', compact('groups'))->render();
        });
    }

    public static function forgetProjectCache(int $projectId): void
    {
        Cache::put('monitoring_child_rows_ver:' . $projectId, (int) Cache::get('monitoring_child_rows_ver:' . $projectId, 0) + 1, 86400);
    }

    private function cacheKey(int $projectId, string $groupKey): string
    {
        $ver = (int) Cache::get('monitoring_child_rows_ver:' . $projectId, 0);

        return sprintf('monitoring_child_rows:%d:%s:v%d', $projectId, $groupKey, $ver);
    }

    /**
     * @return Collection
     */
    private function buildGroups(MonitoringProject $project, $groupId)
    {
        $engines = $project->searchengines()->with('location')->get();
        if ($engines->isEmpty()) {
            return collect([]);
        }

        foreach ($engines as $engine) {
            $engine->setRelation('project', $project);
        }

        $engineIds = $engines->pluck('id')->all();
        $keywordIds = $this->keywordFilterIds($project, $groupId);
        $months = $this->metrics->getSubtractionMonths();

        foreach ($engines as $engine) {
            $engine->data = collect([]);
        }

        $pricesByEngine = $this->keywordPricesByEngine($engineIds, $keywordIds);

        if ($this->shouldLoadPositionsInSingleQuery($project, $engineIds, $keywordIds)) {
            $this->fillGroupsFromSingleQuery($engines, $engineIds, $keywordIds, $months, $pricesByEngine);
        } else {
            $this->fillGroupsByMonthQueries($engines, $engineIds, $keywordIds, $months, $pricesByEngine);
        }

        return $engines;
    }

    /**
     * COUNT по monitoring_positions на удалённой БД — 2–5 с даже для малых проектов.
     * Для типичных портфелей оцениваем объём без COUNT.
     */
    private function shouldLoadPositionsInSingleQuery(MonitoringProject $project, array $engineIds, ?array $keywordIds): bool
    {
        $engineCount = count($engineIds);
        if ($engineCount === 0) {
            return true;
        }

        $keywordCount = $keywordIds !== null
            ? count($keywordIds)
            : (int) $project->keywords()->count();

        if ($engineCount <= 12 && $keywordCount <= 8000) {
            return true;
        }

        $query = MonitoringPosition::query()
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereNotNull('position');
        $this->applySubtractionMonthsFilter($query, $this->metrics->getSubtractionMonths());

        if ($keywordIds !== null) {
            $query->whereIn('monitoring_keyword_id', $keywordIds);
        }

        return (int) $query->count() <= self::SINGLE_QUERY_MAX_ROWS;
    }

    private function positionsBaseQuery(array $engineIds, ?array $keywordIds)
    {
        $query = MonitoringPosition::query()
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereNotNull('position');

        if ($keywordIds !== null) {
            $query->whereIn('monitoring_keyword_id', $keywordIds);
        }

        return $query->select(['id', 'monitoring_searchengine_id', 'monitoring_keyword_id', 'position', 'created_at'])
            ->orderByDesc('created_at');
    }

    /**
     * Только месяцы среза (0/1/3/6/12), не весь год — меньше строк с удалённой БД.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applySubtractionMonthsFilter($query, array $months): void
    {
        $query->where(function ($outer) use ($months) {
            foreach ($months as $subMonth) {
                $target = Carbon::now()->subMonths($subMonth);
                $outer->orWhere(function ($q) use ($target) {
                    $q->whereYear('created_at', $target->year)
                        ->whereMonth('created_at', $target->month);
                });
            }
        });
    }

    /**
     * Одна выборка цен на все ПС проекта (вместо N×5 запросов в calculateTopPercent).
     *
     * @return array<int, Collection>
     */
    private function keywordPricesByEngine(array $engineIds, ?array $keywordIds): array
    {
        if ($engineIds === []) {
            return [];
        }

        $query = MonitoringKeywordPrice::query()->whereIn('monitoring_searchengine_id', $engineIds);
        if ($keywordIds !== null) {
            $query->whereIn('monitoring_keyword_id', $keywordIds);
        }

        $out = [];
        foreach ($query->get() as $row) {
            if (!isset($out[$row->monitoring_searchengine_id])) {
                $out[$row->monitoring_searchengine_id] = collect();
            }
            $out[$row->monitoring_searchengine_id]->put($row->monitoring_keyword_id, $row);
        }

        return $out;
    }

    private function fillGroupsFromSingleQuery($engines, array $engineIds, ?array $keywordIds, array $months, array $pricesByEngine): void
    {
        $query = $this->positionsBaseQuery($engineIds, $keywordIds);
        $this->applySubtractionMonthsFilter($query, $months);
        $all = $query->get()->groupBy('monitoring_searchengine_id');

        foreach ($engines as $engine) {
            $positions = $all->get($engine->id, collect());
            $prices = $pricesByEngine[$engine->id] ?? collect();
            foreach ($months as $month) {
                $monthPositions = $this->filterByMonth($positions, $month);
                if ($monthPositions === null) {
                    continue;
                }
                $engine->data->push($this->metrics->calculateTopPercent($monthPositions, $engine, $prices));
            }
        }
    }

    private function fillGroupsByMonthQueries($engines, array $engineIds, ?array $keywordIds, array $months, array $pricesByEngine): void
    {
        foreach ($months as $month) {
            $target = Carbon::now()->subMonths($month);
            $byEngine = $this->positionsBaseQuery($engineIds, $keywordIds)
                ->whereYear('created_at', $target->year)
                ->whereMonth('created_at', $target->month)
                ->get()
                ->groupBy('monitoring_searchengine_id');

            foreach ($engines as $engine) {
                $monthPositions = $byEngine->get($engine->id);
                if (!$monthPositions || $monthPositions->isEmpty()) {
                    continue;
                }
                $prices = $pricesByEngine[$engine->id] ?? collect();
                $engine->data->push($this->metrics->calculateTopPercent($monthPositions, $engine, $prices));
            }
        }
    }

    /**
     * @param \Illuminate\Support\Collection $positions
     */
    private function filterByMonth($positions, int $subMonth)
    {
        $target = Carbon::now()->subMonths($subMonth);
        $filtered = $positions->filter(function ($row) use ($target) {
            $at = $row->created_at;

            return $at && (int) $at->year === (int) $target->year && (int) $at->month === (int) $target->month;
        })->values();

        return $filtered->isEmpty() ? null : $filtered;
    }

    /**
     * @return int[]|null null = без фильтра по ключевым словам
     */
    private function keywordFilterIds(MonitoringProject $project, $groupId): ?array
    {
        if (!$groupId) {
            return null;
        }

        $section = $project->groups()->find($groupId);
        if (!$section) {
            return null;
        }

        return $section->keywords()->pluck('id')->all();
    }
}
