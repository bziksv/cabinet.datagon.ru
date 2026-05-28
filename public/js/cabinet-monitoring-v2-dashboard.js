/**
 * Дашборд мониторинга v2 — один главный график (SER) + KPI (Topvisor).
 */
(function (window) {
    'use strict';

    const cfg = window.cabinetMonV2Config;
    const COLORS = {
        accent: 'rgba(98, 125, 152, 0.88)',
        accentLight: 'rgba(98, 125, 152, 0.45)',
        accentPale: 'rgba(98, 125, 152, 0.2)',
        buckets: [
            'rgba(155, 44, 44, 0.75)',
            'rgba(196, 120, 74, 0.75)',
            'rgba(180, 160, 90, 0.75)',
            'rgba(98, 125, 152, 0.75)',
            'rgba(45, 106, 79, 0.8)',
        ],
    };

    let mainChart = null;
    let lastFiltered = false;
    let lastRows = [];
    let chartMode = 'leaders';
    let dashMetric = 'top10';
    let lastChartSig = '';

    function chartSignature() {
        return chartMode + '|' + dashMetric;
    }

    function truncateLabel(label) {
        const s = String(label || '');
        return s.length > 28 ? s.slice(0, 26) + '…' : s;
    }

    function topNum(value) {
        if (value == null || value === '') {
            return null;
        }
        const n = parseFloat(String(value).replace('%', '').replace(',', '.'));
        return Number.isNaN(n) ? null : n;
    }

    function middleNum(value) {
        if (value == null || value === '') {
            return null;
        }
        const n = parseFloat(String(value).replace(',', '.'));
        return Number.isNaN(n) ? null : n;
    }

    function avgField(rows, field) {
        let sum = 0;
        let n = 0;
        rows.forEach(function (row) {
            const v = topNum(row[field]);
            if (v !== null && v >= 0) {
                sum += v;
                n += 1;
            }
        });
        return n ? Math.round((sum / n) * 10) / 10 : null;
    }

    function avgMiddle(rows) {
        let sum = 0;
        let n = 0;
        rows.forEach(function (row) {
            const v = middleNum(row.middle);
            if (v !== null && v > 0) {
                sum += v;
                n += 1;
            }
        });
        return n ? Math.round((sum / n) * 10) / 10 : null;
    }

    function sumWords(rows) {
        let sum = 0;
        rows.forEach(function (row) {
            sum += parseInt(row.words, 10) || 0;
        });
        return sum;
    }

    function countWeak(rows, threshold) {
        let c = 0;
        rows.forEach(function (row) {
            const v = topNum(row.top10);
            if (v !== null && v < threshold) {
                c += 1;
            }
        });
        return c;
    }

    function distributionBuckets(rows) {
        const labels = (cfg.i18n.dashBuckets || []).slice(0, 5);
        const counts = [0, 0, 0, 0, 0];
        rows.forEach(function (row) {
            const v = topNum(row.top10);
            if (v === null) {
                return;
            }
            if (v < 20) {
                counts[0] += 1;
            } else if (v < 40) {
                counts[1] += 1;
            } else if (v < 60) {
                counts[2] += 1;
            } else if (v < 80) {
                counts[3] += 1;
            } else {
                counts[4] += 1;
            }
        });
        return { labels: labels, counts: counts };
    }

    function topProjects(rows, limit, metric) {
        const m = metric || dashMetric;
        return rows
            .map(function (row) {
                let value = null;
                if (m === 'middle') {
                    value = middleNum(row.middle);
                } else if (m === 'top30') {
                    value = topNum(row.top30);
                } else {
                    value = topNum(row.top10);
                }
                return {
                    label: row.url || row.name || String(row.id),
                    value: value,
                };
            })
            .filter(function (item) {
                return item.value !== null;
            })
            .sort(function (a, b) {
                if (m === 'middle') {
                    return a.value - b.value;
                }
                return b.value - a.value;
            })
            .slice(0, limit);
    }

    function destroyMain() {
        if (mainChart) {
            mainChart.destroy();
            mainChart = null;
        }
        lastChartSig = '';
    }

    function updateMainChartInPlace(rows) {
        if (!mainChart) {
            return false;
        }

        if (chartMode === 'distribution') {
            const dist = distributionBuckets(rows);
            mainChart.data.labels = dist.labels;
            mainChart.data.datasets[0].data = dist.counts;
            mainChart.update('none');
            return true;
        }

        if (chartMode === 'portfolio') {
            const fields = ['top3', 'top5', 'top10', 'top30'];
            mainChart.data.datasets[0].data = fields.map(function (f) {
                return avgField(rows, f) || 0;
            });
            mainChart.update('none');
            return true;
        }

        const metric = dashMetric;
        const leaders = topProjects(rows, 12, metric);
        const isMiddle = metric === 'middle';
        let chartLabel = cfg.i18n.top + '10';
        if (metric === 'top30') {
            chartLabel = cfg.i18n.top + '30';
        } else if (isMiddle) {
            chartLabel = cfg.i18n.position || 'Position';
        }

        mainChart.data.labels = leaders.map(function (l) {
            return truncateLabel(l.label);
        });
        mainChart.data.datasets[0].label = chartLabel;
        mainChart.data.datasets[0].data = leaders.map(function (l) {
            return l.value;
        });
        if (mainChart.options.scales && mainChart.options.scales.x) {
            if (isMiddle) {
                delete mainChart.options.scales.x.max;
            } else {
                mainChart.options.scales.x.max = 100;
            }
        }
        mainChart.update('none');
        return true;
    }

    function renderMainChart(rows) {
        const canvas = document.getElementById('cabinet-mon-v2-chart-main');
        if (!canvas || typeof window.Chart === 'undefined') {
            return;
        }

        destroyMain();
        const ctx = canvas.getContext('2d');

        if (chartMode === 'distribution') {
            const dist = distributionBuckets(rows);
            mainChart = new window.Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: dist.labels,
                    datasets: [
                        {
                            data: dist.counts,
                            backgroundColor: COLORS.buckets,
                            borderWidth: 1,
                            borderColor: '#fff',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    },
                },
            });
            lastChartSig = chartSignature();
            return;
        }

        if (chartMode === 'portfolio') {
            const labels = ['TOP3', 'TOP5', 'TOP10', 'TOP30'];
            const fields = ['top3', 'top5', 'top10', 'top30'];
            const values = fields.map(function (f) {
                return avgField(rows, f) || 0;
            });
            mainChart = new window.Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: cfg.i18n.dashAvgLabel,
                            data: values,
                            backgroundColor: [COLORS.accentPale, COLORS.accentLight, COLORS.accent, COLORS.accent],
                            borderColor: COLORS.accent,
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            max: 100,
                            ticks: { callback: function (v) { return v + '%'; } },
                        },
                    },
                },
            });
            lastChartSig = chartSignature();
            return;
        }

        const metric = dashMetric;
        const leaders = topProjects(rows, 12, metric);
        const isMiddle = metric === 'middle';
        let chartLabel = cfg.i18n.top + '10';
        if (metric === 'top30') {
            chartLabel = cfg.i18n.top + '30';
        } else if (isMiddle) {
            chartLabel = cfg.i18n.position || 'Position';
        }

        mainChart = new window.Chart(ctx, {
            type: 'bar',
            data: {
                labels: leaders.map(function (l) {
                    return truncateLabel(l.label);
                }),
                datasets: [
                    {
                        label: chartLabel,
                        data: leaders.map(function (l) {
                            return l.value;
                        }),
                        backgroundColor: COLORS.accent,
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: isMiddle
                        ? { ticks: {} }
                        : { max: 100, ticks: { callback: function (v) { return v + '%'; } } },
                },
            },
        });
        lastChartSig = chartSignature();
    }

    function updateChartSmart(rows) {
        const sig = chartSignature();
        if (mainChart && sig === lastChartSig && updateMainChartInPlace(rows)) {
            return;
        }
        renderMainChart(rows);
    }

    function updateStats(rows, filtered) {
        const $root = $('#cabinet-mon-v2-dashboard');
        if (!$root.length) {
            return;
        }

        const avg10 = avgField(rows, 'top10');
        const avgMid = avgMiddle(rows);
        $root.find('[data-dash="projects"]').text(rows.length);
        $root.find('[data-dash="avgTop10"]').text(avg10 !== null ? avg10 + '%' : '—');
        $root.find('[data-dash="avgMiddle"]').text(avgMid !== null ? String(avgMid) : '—');
        $root.find('[data-dash="words"]').text(sumWords(rows).toLocaleString('ru-RU'));
        $root.find('[data-dash="weak"]').text(countWeak(rows, 30));

        const $hint = $('#cabinet-mon-v2-dash-hint');
        if ($hint.length) {
            $hint.text(filtered ? cfg.i18n.dashHintFiltered : cfg.i18n.dashHintAll);
        }
    }

    function syncMetricTabsVisibility() {
        const $metric = $('#cabinet-mon-v2-dash-metric');
        if (!$metric.length) {
            return;
        }
        $metric.toggleClass('d-none', chartMode !== 'leaders');
    }

    function bindControls() {
        const $dash = $('#cabinet-mon-v2-dashboard');
        $dash.find('[data-dash-chart]').on('click', function () {
            const next = $(this).data('dash-chart');
            if (!next || next === chartMode) {
                return;
            }
            chartMode = next;
            $dash.find('[data-dash-chart]').removeClass('active');
            $(this).addClass('active');
            syncMetricTabsVisibility();
            if (lastRows.length) {
                renderMainChart(lastRows);
            }
        });

        $dash.find('[data-dash-metric]').on('click', function () {
            const next = $(this).data('dash-metric');
            if (!next || next === dashMetric) {
                return;
            }
            dashMetric = next;
            $dash.find('[data-dash-metric]').removeClass('active');
            $(this).addClass('active');
            if (lastRows.length && chartMode === 'leaders') {
                renderMainChart(lastRows);
            }
        });

        syncMetricTabsVisibility();
    }

    function render(rows, filtered) {
        if (!$('#cabinet-mon-v2-dashboard').length) {
            return;
        }

        lastFiltered = !!filtered;
        lastRows = rows || [];
        updateStats(lastRows, lastFiltered);

        if (!lastRows.length) {
            destroyMain();
            return;
        }

        updateChartSmart(lastRows);
    }

    function destroy() {
        destroyMain();
    }

    bindControls();

    window.cabinetMonV2Dashboard = {
        render: render,
        destroy: destroy,
    };
})(window);
