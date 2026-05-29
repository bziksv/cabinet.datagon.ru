<?php

namespace App\Classes\Monitoring;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Общие запросы двух проектов/папок для честного сравнения графиков.
 */
class MonitoringCompareKeywordIntersect
{
    public static function normalizeQuery(?string $query): string
    {
        return mb_strtolower(trim((string) $query));
    }

    /**
     * @return Collection<int, string> normalized queries
     */
    public static function intersectedQueries(
        int $projectId,
        ?int $groupId,
        int $otherProjectId,
        ?int $otherGroupId
    ): Collection {
        $left = self::normalizedQueriesForScope($projectId, $groupId);
        $right = self::normalizedQueriesForScope($otherProjectId, $otherGroupId);

        return $left->intersect($right)->values();
    }

    public static function intersectedCount(
        int $projectId,
        ?int $groupId,
        int $otherProjectId,
        ?int $otherGroupId
    ): int {
        return self::intersectedQueries($projectId, $groupId, $otherProjectId, $otherGroupId)->count();
    }

    /**
     * ID ключей текущего проекта, чьи запросы есть в пересечении.
     *
     * @return int[]
     */
    public static function keywordIdsForIntersection(
        int $projectId,
        ?int $groupId,
        int $otherProjectId,
        ?int $otherGroupId
    ): array {
        $queries = self::intersectedQueries($projectId, $groupId, $otherProjectId, $otherGroupId);
        if ($queries->isEmpty()) {
            return [];
        }

        $lookup = array_flip($queries->all());
        $rows = self::keywordsQuery($projectId, $groupId)->get(['id', 'query']);
        $ids = [];
        foreach ($rows as $row) {
            $normalized = self::normalizeQuery($row->query);
            if ($normalized === '' || !isset($lookup[$normalized])) {
                continue;
            }
            $ids[] = (int) $row->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return Collection<int, string>
     */
    private static function normalizedQueriesForScope(int $projectId, ?int $groupId): Collection
    {
        return self::keywordsQuery($projectId, $groupId)
            ->pluck('query')
            ->map(function ($query) {
                return self::normalizeQuery($query);
            })
            ->filter(function ($query) {
                return $query !== '';
            })
            ->unique()
            ->values();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private static function keywordsQuery(int $projectId, ?int $groupId)
    {
        $query = DB::table('monitoring_keywords')->where('monitoring_project_id', $projectId);
        if ($groupId !== null && $groupId > 0) {
            $query->where('monitoring_group_id', $groupId);
        }

        return $query;
    }
}
