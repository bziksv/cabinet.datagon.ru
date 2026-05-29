/**
 * Мониторинг: шкала для средней позиции (как SER — меньше = лучше, линия вверх = рост).
 * Палитра серий — синхрон с App\Classes\Monitoring\MonitoringChartPalette (PHP).
 * DISTRIBUTION_PALETTE — кольцевая «Распределение по ТОП-100».
 */
(function (global) {
    'use strict';

    /** Насыщенные контрастные цвета; не использовать бледные/пастельные тона для линий. */
    var LINE_PALETTE = [
        '#1864ab',
        '#d9480f',
        '#2b8a3e',
        '#862e9c',
        '#c92a2a',
        '#0b7285',
        '#e67700',
        '#343a40',
    ];

    /** Кольцевая «Распределение по ТОП-100» — Bootstrap 5 / AdminLTE 4 (html/index2.html). */
    var DISTRIBUTION_PALETTE = [
        '#0d6efd',
        '#20c997',
        '#0dcaf0',
        '#ffc107',
        '#adb5bd',
        '#dc3545',
    ];

    function paintLineDataset(ds, color) {
        ds.borderColor = color;
        ds.backgroundColor = color;
        ds.pointBackgroundColor = color;
        ds.pointBorderColor = '#ffffff';
        ds.borderWidth = ds.borderWidth != null ? ds.borderWidth : 2.5;
        ds.pointRadius = ds.pointRadius != null ? ds.pointRadius : 3;
        ds.pointHoverRadius = ds.pointHoverRadius != null ? ds.pointHoverRadius : 5;
        ds.pointBorderWidth = ds.pointBorderWidth != null ? ds.pointBorderWidth : 1.5;
    }

    var api = {
        lineColor: function (index) {
            return LINE_PALETTE[Math.abs(index) % LINE_PALETTE.length];
        },

        distributionColors: function () {
            return DISTRIBUTION_PALETTE.slice();
        },

        applyDistributionStyle: function (chartData) {
            if (!chartData || !chartData.datasets || !chartData.datasets[0]) {
                return chartData;
            }
            var ds = chartData.datasets[0];
            var colors = api.distributionColors();
            ds.backgroundColor = colors;
            ds.borderColor = '#ffffff';
            ds.borderWidth = 2;
            ds.hoverBorderColor = '#ffffff';
            return chartData;
        },

        distributionLabelColor: function (backgroundColor) {
            if (backgroundColor === '#ffc107' || backgroundColor === '#adb5bd') {
                return '#212529';
            }
            return '#ffffff';
        },

        /**
         * Перекрасить линейные серии по индексу — обязательно при сравнении проектов
         * и при нескольких сериях с одним ТОП (иначе все синие).
         */
        applyDistinctLineColors: function (chartData) {
            if (!chartData || !chartData.datasets) {
                return chartData;
            }
            var lineIndex = 0;
            chartData.datasets.forEach(function (ds) {
                if (ds.type && ds.type !== 'line') {
                    return;
                }
                paintLineDataset(ds, api.lineColor(lineIndex));
                lineIndex += 1;
            });
            return chartData;
        },

        /** @param {string} metric */
        isPositionMetric: function (metric) {
            return metric === 'position' || metric === 'middle' || metric === 'regions_middle';
        },

        /** Ось Y для линейных графиков средней позиции (Chart.js 3). */
        lineY: function (extra) {
            var y = {
                reverse: true,
                ticks: {
                    stepSize: 5,
                },
            };
            if (extra && typeof extra === 'object') {
                Object.keys(extra).forEach(function (key) {
                    if (key === 'ticks' && extra.ticks && typeof extra.ticks === 'object') {
                        y.ticks = Object.assign({}, y.ticks, extra.ticks);
                    } else {
                        y[key] = extra[key];
                    }
                });
            }
            return y;
        },

        /** Границы Y для child-chart (позиция). */
        positionYBounds: function (datasets) {
            var vals = [];
            (datasets || []).forEach(function (ds) {
                if (ds.hidden) {
                    return;
                }
                (ds.data || []).forEach(function (v) {
                    if (v != null && !isNaN(v)) {
                        vals.push(Number(v));
                    }
                });
            });
            if (!vals.length) {
                return { reverse: true, min: 1, suggestedMax: 50 };
            }
            var minV = Math.min.apply(null, vals);
            var maxV = Math.max.apply(null, vals);
            var span = maxV - minV || 8;
            var pad = Math.max(span * 0.15, 1);
            return {
                reverse: true,
                min: Math.max(1, Math.floor(minV - pad)),
                max: Math.ceil(maxV + pad),
            };
        },

        applyToChart: function (chart, isPosition) {
            if (!chart || !chart.options || !chart.options.scales) {
                return;
            }
            if (chart.options.scales.y) {
                chart.options.scales.y.reverse = !!isPosition;
            }
            if (chart.options.scales.x && chart.options.indexAxis === 'y') {
                chart.options.scales.x.reverse = !!isPosition;
            }
        },

        /** Соединять линией разрозненные точки (пропуски = null в данных). */
        applySpanGaps: function (chartData) {
            if (!chartData || !chartData.datasets) {
                return chartData;
            }
            chartData.datasets.forEach(function (ds) {
                if (ds.type && ds.type !== 'line') {
                    return;
                }
                ds.spanGaps = true;
                if (ds.pointRadius == null) {
                    ds.pointRadius = 3;
                }
                if (ds.pointHoverRadius == null) {
                    ds.pointHoverRadius = 5;
                }
            });
            return chartData;
        },
    };

    global.cabinetMonitoringChartScales = api;
})(typeof window !== 'undefined' ? window : this);
