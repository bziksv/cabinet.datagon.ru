<?php

namespace App\SeoReports;

/**
 * Цели периода проекта и расчёт % выполнения по снимку отчёта.
 */
class SeoReportKpiGoals
{
    public const TYPES = ['visits', 'users', 'top10', 'conversions', 'revenue'];

    /**
     * @param array<string,mixed>|null $settings
     * @return list<array{type:string,label:string,target:float,enabled:bool}>
     */
    public static function fromSettings(?array $settings): array
    {
        $raw = is_array($settings['kpi_goals'] ?? null) ? $settings['kpi_goals'] : [];
        $out = [];
        foreach (self::TYPES as $type) {
            $row = is_array($raw[$type] ?? null) ? $raw[$type] : [];
            $target = isset($row['target']) ? (float) $row['target'] : 0.0;
            $enabled = !empty($row['enabled']) && $target > 0;
            $out[] = [
                'type' => $type,
                'label' => self::label($type),
                'target' => $target,
                'enabled' => $enabled,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,array{enabled:bool,target:float}>
     */
    public static function normalizeInput($input): array
    {
        if (!is_array($input)) {
            $input = [];
        }
        $out = [];
        foreach (self::TYPES as $type) {
            $row = is_array($input[$type] ?? null) ? $input[$type] : [];
            $target = max(0, (float) str_replace(',', '.', (string) ($row['target'] ?? 0)));
            $out[$type] = [
                'enabled' => !empty($row['enabled']) && $target > 0,
                'target' => $target,
            ];
        }

        return $out;
    }

    public static function label(string $type): string
    {
        $map = [
            'visits' => __('Visits'),
            'users' => __('Users'),
            'top10' => 'TOP-10',
            'conversions' => __('Goal reaches'),
            'revenue' => __('Revenue'),
        ];

        return $map[$type] ?? $type;
    }

    /**
     * Подсказки для мастера/настроек: единица, пример цели, демо светофора.
     *
     * @return list<array{
     *   type:string,
     *   label:string,
     *   unit:string,
     *   hint:string,
     *   example:int,
     *   placeholder:string,
     *   demo_actual:int,
     *   demo_pct:float,
     *   demo_tone:string
     * }>
     */
    public static function wizardRows(): array
    {
        return [
            [
                'type' => 'visits',
                'label' => self::label('visits'),
                'unit' => 'визитов за месяц',
                'hint' => 'Сколько визитов хотите набрать за период отчёта.',
                'example' => 15000,
                'placeholder' => 'напр. 15 000',
                'demo_actual' => 12480,
                'demo_pct' => 83.2,
                'demo_tone' => 'yellow',
            ],
            [
                'type' => 'users',
                'label' => self::label('users'),
                'unit' => 'пользователей за месяц',
                'hint' => 'Уникальные посетители из Метрики.',
                'example' => 9000,
                'placeholder' => 'напр. 9 000',
                'demo_actual' => 8120,
                'demo_pct' => 90.2,
                'demo_tone' => 'yellow',
            ],
            [
                'type' => 'top10',
                'label' => self::label('top10'),
                'unit' => 'запросов в TOP-10',
                'hint' => 'Сколько фраз должно быть в первой десятке Яндекс/Google.',
                'example' => 40,
                'placeholder' => 'напр. 40',
                'demo_actual' => 42,
                'demo_pct' => 105.0,
                'demo_tone' => 'green',
            ],
            [
                'type' => 'conversions',
                'label' => self::label('conversions'),
                'unit' => 'достижений целей Метрики',
                'hint' => 'Сумма достижений выбранных целей за период.',
                'example' => 200,
                'placeholder' => 'напр. 200',
                'demo_actual' => 118,
                'demo_pct' => 59.0,
                'demo_tone' => 'red',
            ],
        ];
    }

    /**
     * @param list<array{type:string,label:string,target:float,enabled:bool}> $goals
     * @param array<string,mixed> $snapshot
     * @return list<array<string,mixed>>
     */
    public static function evaluate(array $goals, array $snapshot): array
    {
        $actuals = self::actualsFromSnapshot($snapshot);
        $out = [];
        foreach ($goals as $goal) {
            if (empty($goal['enabled'])) {
                continue;
            }
            $type = (string) $goal['type'];
            $target = (float) $goal['target'];
            $actual = $actuals[$type] ?? null;
            $pct = null;
            if ($actual !== null && $target > 0) {
                $pct = round(($actual / $target) * 100, 1);
            }
            $tone = 'yellow';
            $why = __('No actual value yet');
            if ($pct !== null) {
                if ($pct >= 100) {
                    $tone = 'green';
                    $why = __('Goal reached or exceeded');
                } elseif ($pct >= 70) {
                    $tone = 'yellow';
                    $why = __('Close to target — keep current pace');
                } else {
                    $tone = 'red';
                    $why = __('Below target — review traffic and landing pages');
                }
            }
            $out[] = [
                'type' => $type,
                'label' => $goal['label'],
                'target' => $target,
                'actual' => $actual,
                'pct' => $pct,
                'tone' => $tone,
                'why' => $why,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,float|null>
     */
    private static function actualsFromSnapshot(array $snapshot): array
    {
        $traffic = is_array($snapshot['traffic'] ?? null) ? $snapshot['traffic'] : [];
        $positions = is_array($snapshot['positions'] ?? null) ? $snapshot['positions'] : [];
        $conversions = is_array($snapshot['conversions'] ?? null) ? $snapshot['conversions'] : [];

        $convTotal = null;
        if (!empty($conversions['goals']) && is_array($conversions['goals'])) {
            $convTotal = 0.0;
            foreach ($conversions['goals'] as $g) {
                $convTotal += (float) ($g['reaches']['value'] ?? 0);
            }
        }

        $top10 = $positions['summary']['top10'] ?? null;

        return [
            'visits' => isset($traffic['kpis']['visits']['value'])
                ? (float) $traffic['kpis']['visits']['value']
                : null,
            'users' => isset($traffic['kpis']['users']['value'])
                ? (float) $traffic['kpis']['users']['value']
                : null,
            'top10' => $top10 !== null && $top10 !== '' ? (float) $top10 : null,
            'conversions' => $convTotal,
            'revenue' => null,
        ];
    }
}
