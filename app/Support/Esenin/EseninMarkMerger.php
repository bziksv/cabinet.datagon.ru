<?php

namespace App\Support\Esenin;

final class EseninMarkMerger
{
    /**
     * @param array<int, array<string, mixed>> $base
     * @param array<int, array<string, mixed>> $extra
     * @return array<int, array<string, mixed>>
     */
    public static function merge(array $base, array $extra): array
    {
        if ($extra === []) {
            return $base;
        }

        $combined = array_merge($base, $extra);
        usort($combined, static function ($a, $b) {
            return ((int) $a['offset'] <=> (int) $b['offset']) ?: ((int) $b['length'] <=> (int) $a['length']);
        });

        $occupied = [];
        $accepted = [];
        foreach ($combined as $mark) {
            $start = (int) ($mark['offset'] ?? 0);
            $end = $start + (int) ($mark['length'] ?? 0);
            $overlap = false;
            for ($i = $start; $i < $end; $i++) {
                if (! empty($occupied[$i])) {
                    $overlap = true;
                    break;
                }
            }
            if ($overlap) {
                continue;
            }
            for ($i = $start; $i < $end; $i++) {
                $occupied[$i] = true;
            }
            $accepted[] = $mark;
        }

        return $accepted;
    }
}
