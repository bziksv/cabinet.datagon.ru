<?php
namespace App\Services\Wordstat;

class WordstatEstimator
{
    public static function estimate(int $seedCount, array $settings): array
    {
        $seeds = max(0, $seedCount);
        $depth = ((int) ($settings['depth'] ?? 0)) > 0 ? (int) ($settings['depth_max_seeds'] ?? 0) : 0;

        return ['seeds' => $seeds, 'depth' => $depth, 'total' => $seeds + $depth];
    }
}
