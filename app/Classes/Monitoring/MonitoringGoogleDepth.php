<?php

namespace App\Classes\Monitoring;

use App\MonitoringSearchengine;

class MonitoringGoogleDepth
{
    public const MIN = 10;

    public const MAX = 100;

    public const STEP = 10;

    public static function normalize(?int $depth): int
    {
        $depth = (int) ($depth ?: self::MIN);
        $depth = max(self::MIN, min(self::MAX, $depth));

        return (int) (ceil($depth / self::STEP) * self::STEP);
    }

    public static function pageCount(?int $depth): int
    {
        return (int) (self::normalize($depth) / self::STEP);
    }

    public static function limitsMultiplier(?int $depth): int
    {
        return self::pageCount($depth);
    }

    /**
     * @param iterable<MonitoringSearchengine> $engines
     */
    public static function countPositionJobs(int $keywordCount, $engines, ?int $googleDepthOverride = null): int
    {
        $total = 0;

        foreach ($engines as $engine) {
            if ($engine->engine === 'google') {
                $depth = $googleDepthOverride ?? $engine->google_depth ?? self::MIN;
                $total += $keywordCount * self::limitsMultiplier($depth);
            } else {
                $total += $keywordCount;
            }
        }

        return $total;
    }

    /**
     * @return int[]
     */
    public static function options(): array
    {
        return range(self::MIN, self::MAX, self::STEP);
    }
}
