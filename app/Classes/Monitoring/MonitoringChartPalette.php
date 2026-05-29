<?php

namespace App\Classes\Monitoring;

/**
 * Палитра линий графиков мониторинга — насыщенные контрастные тона.
 * Синхрон с public/js/cabinet-monitoring-chart-scales.js (LINE_PALETTE).
 *
 * @see .cursor/rules/redbox-cabinet-charts.mdc
 */
class MonitoringChartPalette
{
    /** @var string[] */
    private const LINE = [
        '#1864ab',
        '#d9480f',
        '#2b8a3e',
        '#862e9c',
        '#c92a2a',
        '#0b7285',
        '#e67700',
        '#343a40',
    ];

    /**
     * Кольцевая «Распределение по ТОП-100» — Bootstrap 5 / AdminLTE 4 (html/index2.html, pie-chart).
     * Порядок: ТОП 3 → ТОП 10 → ТОП 30 → ТОП 50 → ТОП 100 → ТОП 101+.
     *
     * @var string[]
     */
    private const DISTRIBUTION = [
        '#0d6efd', // primary — лучшие позиции
        '#20c997', // teal
        '#0dcaf0', // info
        '#ffc107', // warning
        '#adb5bd', // secondary
        '#dc3545', // danger — вне ТОП-100
    ];

    public static function lineColor(int $index): string
    {
        $palette = self::LINE;

        return $palette[$index % count($palette)];
    }

    /**
     * @return string[]
     */
    public static function distributionColors(): array
    {
        return self::DISTRIBUTION;
    }
}
