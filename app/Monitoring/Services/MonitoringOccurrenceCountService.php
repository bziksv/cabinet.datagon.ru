<?php

namespace App\Monitoring\Services;

use App\MonitoringOccurrence;
use App\MonitoringProject;
use Illuminate\Support\Collection;

class MonitoringOccurrenceCountService
{
    public const LIMITS_PER_PAIR = 3;

    public function projectStats(MonitoringProject $project): array
    {
        $yandexRegionIds = $project->searchengines()->where('engine', 'yandex')->pluck('id');
        $keywordCount = (int) $project->keywords()->count();
        $regionCount = $yandexRegionIds->count();
        $pairsAll = $regionCount * $keywordCount;
        $pairsMissing = $this->countMissingPairs($project->id, $yandexRegionIds, null);

        return $this->buildStats($regionCount, $keywordCount, $pairsAll, $pairsMissing);
    }

    /**
     * @param int[] $keywordIds
     */
    public function keysStats(MonitoringProject $project, array $keywordIds, int $regionId): array
    {
        $keywordIds = $project->keywords()
            ->whereIn('id', array_values(array_map('intval', $keywordIds)))
            ->pluck('id');

        $pairsAll = $keywordIds->count();
        $pairsMissing = $this->countMissingPairs($project->id, collect([$regionId]), $keywordIds);

        return $this->buildStats(1, $pairsAll, $pairsAll, $pairsMissing);
    }

    private function buildStats(int $regionCount, int $keywordCount, int $pairsAll, int $pairsMissing): array
    {
        $pairsMissing = min($pairsMissing, $pairsAll);

        return [
            'yandex_regions' => $regionCount,
            'keywords' => $keywordCount,
            'pairs_all' => $pairsAll,
            'pairs_missing' => $pairsMissing,
            'limits_all' => $pairsAll * self::LIMITS_PER_PAIR,
            'limits_missing' => $pairsMissing * self::LIMITS_PER_PAIR,
            'limits_per_pair' => self::LIMITS_PER_PAIR,
        ];
    }

    /**
     * @param Collection<int, int>|int[] $regionIds
     * @param Collection<int, int>|int[]|null $keywordIds
     */
    private function countMissingPairs(int $projectId, $regionIds, $keywordIds): int
    {
        $regionIds = collect($regionIds)->map(static function ($id) {
            return (int) $id;
        })->filter(static function ($id) {
            return $id > 0;
        })->values();

        if ($regionIds->isEmpty()) {
            return 0;
        }

        $keywordQuery = \App\MonitoringKeyword::query()
            ->where('monitoring_project_id', $projectId);

        if ($keywordIds !== null) {
            $keywordIds = collect($keywordIds)->map(static function ($id) {
                return (int) $id;
            })->filter(static function ($id) {
                return $id > 0;
            })->values();

            if ($keywordIds->isEmpty()) {
                return 0;
            }

            $keywordQuery->whereIn('id', $keywordIds);
        }

        $keywordCount = (int) $keywordQuery->count();
        if ($keywordCount === 0) {
            return 0;
        }

        $existingPairs = MonitoringOccurrence::query()
            ->whereIn('monitoring_searchengine_id', $regionIds)
            ->whereIn('monitoring_keyword_id', function ($query) use ($projectId, $keywordIds) {
                $query->select('id')
                    ->from('monitoring_keywords')
                    ->where('monitoring_project_id', $projectId);

                if ($keywordIds !== null) {
                    $query->whereIn('id', $keywordIds);
                }
            })
            ->count();

        return max(0, $regionIds->count() * $keywordCount - $existingPairs);
    }
}
