/**
 * Графики регионов в child-rows (/monitoring, /monitoring-v2).
 */
(function ($, window) {
    'use strict';

    const DEFAULTS = {
        chartsUrl: '/monitoring/charts',
        i18n: {
            childChartShow: 'Show chart',
            childChartHide: 'Hide chart',
            loadError: 'Failed to load chart',
        },
    };

    let wireConfig = DEFAULTS;

    const childRegionChartStore =
        typeof WeakMap !== 'undefined' ? new WeakMap() : null;
    const childRegionChartStoreFallback = new Map();

    function resolveConfig(options) {
        return Object.assign(
            {},
            DEFAULTS,
            window.cabinetMonitoringChildChartsConfig || {},
            wireConfig,
            options || {}
        );
    }

    function cfg() {
        return resolveConfig();
    }

    function childChartStoreGet(canvas) {
        if (!canvas) {
            return null;
        }
        if (childRegionChartStore) {
            return childRegionChartStore.get(canvas);
        }
        return childRegionChartStoreFallback.get(canvas);
    }

    function childChartStoreSet(canvas, chart) {
        if (!canvas) {
            return;
        }
        if (childRegionChartStore) {
            childRegionChartStore.set(canvas, chart);
        } else {
            childRegionChartStoreFallback.set(canvas, chart);
        }
    }

    function childChartStoreDelete(canvas) {
        if (!canvas) {
            return;
        }
        if (childRegionChartStore) {
            childRegionChartStore.delete(canvas);
        } else {
            childRegionChartStoreFallback.delete(canvas);
        }
    }

    function childChartTopNumberFromLabel(label) {
        const m = String(label || '').match(/(?:топ|top)[-\s]*(\d+)/i);
        if (!m) {
            return null;
        }
        const n = parseInt(m[1], 10);
        return Number.isNaN(n) ? null : n;
    }

    function childChartColorForLabel(label) {
        const n = childChartTopNumberFromLabel(label);
        const byNum = {
            1: '#e03131',
            3: '#f76707',
            5: '#2f9e44',
            10: '#1971c2',
            20: '#9c36b5',
            30: '#c92a2a',
            40: '#ae3ec9',
            50: '#e67700',
            100: '#495057',
        };
        if (n != null && byNum[n]) {
            return byNum[n];
        }
        return '#1971c2';
    }

    function childChartPresetAllowedTops(preset) {
        if (window.cabinetMonV2ChartSettings && window.cabinetMonV2ChartSettings.presetTopNumbers) {
            return window.cabinetMonV2ChartSettings.presetTopNumbers(preset);
        }
        if (preset === '1') {
            return [1];
        }
        if (preset === '3') {
            return [3];
        }
        if (preset === '10') {
            return [10];
        }
        if (preset === '351020100' || preset === '51020') {
            return [3, 5, 10, 20, 100];
        }
        if (preset === '35102050100') {
            return null;
        }
        if (preset === '35102030100') {
            return [3, 5, 10, 20, 30, 100];
        }
        return null;
    }

    function childChartDatasetMatchesPreset(label, preset) {
        const n = childChartTopNumberFromLabel(label);
        if (n == null) {
            return false;
        }
        const allowed = childChartPresetAllowedTops(preset);
        if (allowed === null) {
            return true;
        }
        return allowed.indexOf(n) >= 0;
    }

    function styleChildChartDataset(ds, metric, seriesIndex) {
        const color =
            metric === 'position'
                ? window.cabinetMonitoringChartScales
                    ? window.cabinetMonitoringChartScales.lineColor(seriesIndex || 0)
                    : '#1971c2'
                : childChartColorForLabel(ds.label);
        return {
            label: ds.label,
            data: ds.data,
            borderColor: color,
            backgroundColor: color,
            borderWidth: 3,
            borderDash: [],
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: color,
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            tension: 0.15,
            spanGaps: true,
            fill: metric === 'position',
            hidden: false,
        };
    }

    function normalizeChildChartApiData(apiData, preset, metric) {
        let datasets = (apiData && apiData.datasets) || [];
        if (metric === 'position') {
            datasets = datasets.length ? [datasets[0]] : [];
        } else {
            datasets = datasets.filter(function (ds) {
                return childChartDatasetMatchesPreset(ds.label, preset);
            });
        }
        return {
            labels: (apiData && apiData.labels) || [],
            datasets: datasets.map(function (ds, idx) {
                return styleChildChartDataset(ds, metric, idx);
            }),
        };
    }

    function childChartNumericValues(datasets) {
        const vals = [];
        datasets.forEach(function (ds) {
            if (ds.hidden) {
                return;
            }
            (ds.data || []).forEach(function (v) {
                if (v != null && !isNaN(v)) {
                    vals.push(v);
                }
            });
        });
        return vals;
    }

    function childChartYScaleOptions(datasets, metric) {
        if (metric !== 'position') {
            return { min: 0, max: 100, reverse: false };
        }
        if (window.cabinetMonitoringChartScales && window.cabinetMonitoringChartScales.positionYBounds) {
            return window.cabinetMonitoringChartScales.positionYBounds(datasets);
        }
        const vals = childChartNumericValues(datasets);
        if (!vals.length) {
            return { reverse: true, min: 1, suggestedMax: 50 };
        }
        const minV = Math.min.apply(null, vals);
        const maxV = Math.max.apply(null, vals);
        const span = maxV - minV || 8;
        const pad = Math.max(span * 0.15, 1);
        return {
            reverse: true,
            min: Math.max(1, Math.floor(minV - pad)),
            max: Math.ceil(maxV + pad),
        };
    }

    function getChartSettings() {
        if (window.cabinetMonV2ChartSettings) {
            return window.cabinetMonV2ChartSettings.get();
        }
        return { periodDays: 90, range: 'weeks', metric: 'top', seriesPreset: '10' };
    }

    function childChartDateRangeParam(chartCfg) {
        const days = (chartCfg && chartCfg.periodDays) || 90;
        if (typeof moment === 'undefined') {
            return '';
        }
        const end = moment();
        const start = moment().subtract(days, 'days');
        return start.format('DD-MM-YYYY') + ' - ' + end.format('DD-MM-YYYY');
    }

    function getWrapChartSettings($wrap) {
        const stored = $wrap.data('chartSettings');
        if (stored) {
            return Object.assign({}, stored);
        }
        return getChartSettings();
    }

    function readChildSeriesPreset($wrap) {
        const $active = $wrap.find('[data-child-chart-setting="seriesPreset"].active');
        return $active.data('series-preset') || '10';
    }

    function syncChildChartControls($wrap, settings) {
        const s = settings || getWrapChartSettings($wrap);
        $wrap.find('.cabinet-mon-v2-child-chart-period').val(String(s.periodDays));
        $wrap.find('.cabinet-mon-v2-child-chart-range').val(s.range);
        $wrap.find('.cabinet-mon-v2-child-chart-metric').val(s.metric);
        $wrap.find('[data-child-chart-setting="seriesPreset"]').each(function () {
            $(this).toggleClass('active', $(this).data('series-preset') === s.seriesPreset);
        });
        $wrap.find('.cabinet-mon-v2-child-chart-series-presets').toggleClass('d-none', s.metric === 'position');
    }

    function wireChildChartControls($wrap) {
        if ($wrap.data('chartControlsWired')) {
            return;
        }
        $wrap.data('chartControlsWired', 1);
        syncChildChartControls($wrap, getWrapChartSettings($wrap));

        $wrap.on(
            'change',
            '.cabinet-mon-v2-child-chart-period, .cabinet-mon-v2-child-chart-range, .cabinet-mon-v2-child-chart-metric',
            function () {
                const next = {
                    periodDays: parseInt($wrap.find('.cabinet-mon-v2-child-chart-period').val(), 10),
                    range: $wrap.find('.cabinet-mon-v2-child-chart-range').val(),
                    metric: $wrap.find('.cabinet-mon-v2-child-chart-metric').val(),
                    seriesPreset: readChildSeriesPreset($wrap),
                };
                $wrap.data('chartSettings', next);
                $wrap.attr('data-chart-local', '1');
                syncChildChartControls($wrap, next);
                if (!$wrap.hasClass('d-none')) {
                    fetchChildChartData($wrap);
                }
            }
        );

        $wrap.on('click', '[data-child-chart-setting="seriesPreset"]', function () {
            const preset = $(this).data('series-preset');
            $wrap.find('[data-child-chart-setting="seriesPreset"]').removeClass('active');
            $(this).addClass('active');
            const next = {
                periodDays: parseInt($wrap.find('.cabinet-mon-v2-child-chart-period').val(), 10),
                range: $wrap.find('.cabinet-mon-v2-child-chart-range').val(),
                metric: $wrap.find('.cabinet-mon-v2-child-chart-metric').val(),
                seriesPreset: preset,
            };
            $wrap.data('chartSettings', next);
            $wrap.attr('data-chart-local', '1');
            if (!$wrap.hasClass('d-none')) {
                fetchChildChartData($wrap);
            }
        });

        $wrap.on('click', '.cabinet-mon-v2-child-chart-reset-global', function () {
            $wrap.removeData('chartSettings');
            $wrap.removeAttr('data-chart-local');
            syncChildChartControls($wrap, getChartSettings());
            if (!$wrap.hasClass('d-none')) {
                fetchChildChartData($wrap);
            }
        });
    }

    function ensureChildRegionChart(canvas, metric) {
        let chart = childChartStoreGet(canvas);
        const isPercent = metric === 'top';
        const isPosition = metric === 'position';
        if (chart) {
            return chart;
        }
        chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: { labels: [], datasets: [] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: false,
                        position: 'top',
                        labels: { usePointStyle: true, boxWidth: 12, padding: 16 },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                const y = ctx.parsed.y;
                                if (y == null) {
                                    return ctx.dataset.label;
                                }
                                return isPercent
                                    ? ctx.dataset.label + ': ' + y + '%'
                                    : ctx.dataset.label + ': ' + y;
                            },
                        },
                    },
                },
                scales: {
                    y: isPosition
                        ? (window.cabinetMonitoringChartScales
                            ? window.cabinetMonitoringChartScales.lineY({ grid: { color: 'rgba(0, 0, 0, 0.07)' } })
                            : { reverse: true, grid: { color: 'rgba(0, 0, 0, 0.07)' } })
                        : {
                            min: 0,
                            max: 100,
                            reverse: false,
                            ticks: {
                                callback: function (v) {
                                    return v + '%';
                                },
                            },
                            grid: { color: 'rgba(0, 0, 0, 0.07)' },
                        },
                    x: {
                        ticks: { maxRotation: 45, minRotation: 0 },
                        grid: { display: false },
                    },
                },
            },
        });
        childChartStoreSet(canvas, chart);
        return chart;
    }

    function updateChildRegionChart(canvas, chartData, metric, preset) {
        const datasets = chartData.datasets || [];
        const yBounds = childChartYScaleOptions(datasets, metric);
        const isPercent = metric === 'top';
        const chart = ensureChildRegionChart(canvas, metric, preset);
        chart.data.labels = chartData.labels || [];
        chart.data.datasets = datasets;
        chart.options.plugins.legend.display =
            metric === 'top' && (chart.data.datasets || []).length > 1;
        chart.options.scales.y.min = yBounds.min;
        chart.options.scales.y.max = yBounds.max;
        chart.options.scales.y.reverse = !!yBounds.reverse;
        chart.options.scales.y.ticks.callback = function (v) {
            return isPercent ? v + '%' : v;
        };
        chart.update();
    }

    function resolveChildChartProjectId($wrap) {
        const fromWrap = $wrap.attr('data-project-id') || $wrap.data('projectId');
        if (fromWrap) {
            return fromWrap;
        }
        const $card = $wrap.closest('.cabinet-mon-v2-card');
        if ($card.length) {
            return $card.data('project-id');
        }
        return null;
    }

    function fetchChildChartData($wrap) {
        const canvas = $wrap.find('canvas.cabinet-mon-v2-child-chart').get(0);
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }
        const projectId = resolveChildChartProjectId($wrap);
        const engineId = $wrap.attr('data-engine-id');
        if (!projectId || !engineId) {
            return;
        }

        const chartCfg = getWrapChartSettings($wrap);
        const metric = chartCfg.metric || 'top';
        const preset = chartCfg.seriesPreset || '10';
        const chartType = metric === 'position' ? 'middle' : 'top';
        const rangeVal = chartCfg.range || 'weeks';
        const config = cfg();

        $wrap.addClass('cabinet-mon-v2-child-chart-wrap--loading');

        $.get(config.chartsUrl, {
            projectId: projectId,
            regionId: engineId,
            dateRange: childChartDateRangeParam(chartCfg),
            range: rangeVal,
            chart: chartType,
        })
            .done(function (apiData) {
                const normalized = normalizeChildChartApiData(apiData, preset, metric);
                updateChildRegionChart(canvas, normalized, metric, preset);
            })
            .fail(function () {
                if (typeof toastr !== 'undefined') {
                    toastr.error(config.i18n.loadError);
                }
            })
            .always(function () {
                $wrap.removeClass('cabinet-mon-v2-child-chart-wrap--loading');
            });
    }

    function wireChildChartPanel($wrap) {
        wireChildChartControls($wrap);
        $wrap.data('chartPanelWired', 1);
    }

    function destroyChildRegionChart(canvas) {
        const chart = childChartStoreGet(canvas);
        if (chart) {
            chart.destroy();
            childChartStoreDelete(canvas);
        }
    }

    function assignChildChartProjectIds($root, projectId) {
        if (!projectId) {
            return;
        }
        $root.find('.cabinet-mon-v2-child-chart-wrap').each(function () {
            const $w = $(this);
            if (!$w.attr('data-project-id')) {
                $w.attr('data-project-id', projectId);
            }
        });
    }

    function wire($root, projectId, options) {
        wireConfig = resolveConfig(options);
        const $scope = $root && $root.jquery ? $root : $($root);
        assignChildChartProjectIds($scope, projectId);
        const i18n = cfg().i18n;

        $scope.find('.cabinet-mon-v2-child-chart-toggle').each(function () {
            const $btn = $(this);
            if ($btn.data('wired')) {
                return;
            }
            $btn.data('wired', 1);
            const $card = $btn.closest('.card, .cabinet-mon-v2-card');
            const $wrap = $card.find('.cabinet-mon-v2-child-chart-wrap').first();
            const $label = $btn.find('.cabinet-mon-v2-child-chart-toggle-label');
            $btn.on('click', function () {
                const hidden = $wrap.hasClass('d-none');
                if (hidden) {
                    $wrap.removeClass('d-none');
                    $label.text(i18n.childChartHide);
                    wireChildChartPanel($wrap);
                    fetchChildChartData($wrap);
                } else {
                    $wrap.addClass('d-none');
                    $label.text(i18n.childChartShow);
                    destroyChildRegionChart($wrap.find('canvas.cabinet-mon-v2-child-chart').get(0));
                }
            });
        });
    }

    function refreshOpenCharts() {
        $('.cabinet-mon-v2-child-chart-wrap:not(.d-none)').each(function () {
            const $wrap = $(this);
            if ($wrap.attr('data-chart-local')) {
                return;
            }
            syncChildChartControls($wrap, getChartSettings());
            fetchChildChartData($wrap);
        });
    }

    window.cabinetMonitoringChildCharts = {
        wire: wire,
        refreshOpenCharts: refreshOpenCharts,
    };

    $(document).on('cabinet-mon-v2-chart-settings-changed', function () {
        refreshOpenCharts();
    });
})(jQuery, window);
