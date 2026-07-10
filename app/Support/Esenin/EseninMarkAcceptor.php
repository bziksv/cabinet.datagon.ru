<?php

namespace App\Support\Esenin;

final class EseninMarkAcceptor
{
    /**
     * @param  array<int, array<string, mixed>>  $marks
     * @return array<int, array<string, mixed>>
     */
    public static function accept(array $marks, string $block): array
    {
        if ($marks === []) {
            return [];
        }

        $filtered = array_values(array_filter($marks, static function ($mark) use ($block) {
            if ($block === 'risk') {
                return true;
            }

            return ($mark['block'] ?? '') === $block;
        }));

        usort($filtered, static function ($a, $b) {
            return ($a['offset'] <=> $b['offset']) ?: ($b['length'] <=> $a['length']);
        });

        $occupied = [];
        $accepted = [];
        foreach ($filtered as $mark) {
            $start = (int) $mark['offset'];
            $end = $start + (int) $mark['length'];
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

        usort($accepted, static function ($a, $b) {
            return $b['offset'] <=> $a['offset'];
        });

        return $accepted;
    }
}
