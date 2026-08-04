/**
 * Charts for SEO reports (Chart.js 3): day bars + share doughnuts.
 */
(function () {
    'use strict';

    function formatDateLabel(iso) {
        if (!iso || typeof iso !== 'string') return iso || '';
        var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return iso;
        return m[3] + '.' + m[2];
    }

    function formatDateFull(iso) {
        if (!iso || typeof iso !== 'string') return iso || '';
        var m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!m) return iso;
        return m[3] + '.' + m[2] + '.' + m[1];
    }

    function formatNum(v) {
        var n = Math.round(Number(v) || 0);
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function accentColor(el) {
        var fromData = (el.getAttribute('data-sr-chart-color') || '').trim();
        if (fromData) return fromData;
        try {
            var root = document.querySelector('.cabinet-sr-report, .cabinet-sr-page, .cabinet-sr-public') || document.documentElement;
            var cs = getComputedStyle(root);
            var c = (cs.getPropertyValue('--sr-accent') || '').trim();
            if (c) return c;
        } catch (e) { /* ignore */ }
        return '#0d9488';
    }

    function hexToRgba(hex, a) {
        var h = String(hex || '').replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        if (h.length !== 6) return 'rgba(13, 148, 136, ' + a + ')';
        var r = parseInt(h.slice(0, 2), 16);
        var g = parseInt(h.slice(2, 4), 16);
        var b = parseInt(h.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + a + ')';
    }

    function initDayCanvas(canvas) {
        if (!canvas || canvas._srChart || typeof Chart === 'undefined') return;
        var labels;
        var values;
        try {
            labels = JSON.parse(canvas.getAttribute('data-sr-chart-labels') || '[]');
            values = JSON.parse(canvas.getAttribute('data-sr-chart-values') || '[]');
        } catch (e) {
            return;
        }
        if (!labels.length) return;

        var color = accentColor(canvas);
        var unit = canvas.getAttribute('data-sr-chart-unit') || '';
        var title = canvas.getAttribute('data-sr-chart-title') || '';
        var tickEvery = labels.length > 20 ? 5 : (labels.length > 12 ? 3 : 2);

        canvas._srChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: title || unit || '',
                    data: values,
                    backgroundColor: hexToRgba(color, 0.72),
                    hoverBackgroundColor: color,
                    borderRadius: 3,
                    borderSkipped: false,
                    maxBarThickness: 28,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { weight: '600', size: 12 },
                        bodyFont: { size: 13 },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            title: function (items) {
                                var i = items[0];
                                return formatDateFull(i && i.label);
                            },
                            label: function (item) {
                                var v = formatNum(item.raw);
                                return unit ? (v + ' ' + unit) : v;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' },
                            callback: function (val, idx) {
                                if (idx === 0 || idx === labels.length - 1 || idx % tickEvery === 0) {
                                    return formatDateLabel(labels[idx]);
                                }
                                return '';
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grace: '8%',
                        grid: {
                            color: '#e8edf3',
                            drawBorder: false,
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' },
                            callback: function (v) {
                                return formatNum(v);
                            },
                        },
                    },
                },
            },
        });
    }

    function initCompare(wrap) {
        if (!wrap || typeof Chart === 'undefined') return;
        var canvas = wrap.querySelector('canvas');
        if (!canvas || canvas._srChart) return;
        var labels;
        var prev;
        var cur;
        var deltas;
        try {
            labels = JSON.parse(canvas.getAttribute('data-sr-compare-labels') || '[]');
            prev = JSON.parse(canvas.getAttribute('data-sr-compare-prev') || '[]');
            cur = JSON.parse(canvas.getAttribute('data-sr-compare-cur') || '[]');
            deltas = JSON.parse(canvas.getAttribute('data-sr-compare-deltas') || '[]');
        } catch (e) {
            return;
        }
        if (!labels.length) return;

        var prevLabel = canvas.getAttribute('data-sr-compare-prev-label') || '';
        var curLabel = canvas.getAttribute('data-sr-compare-cur-label') || '';
        var colorPrev = '#f5c896';
        var colorCur = '#8fd4cb';

        // Подписи на столбцах без chartjs-plugin-datalabels (он ломал первый layout)
        var valueLabelsPlugin = {
            id: 'srCompareValueLabels',
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx;
                ctx.save();
                ctx.fillStyle = '#334155';
                ctx.font = '700 11px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                chart.data.datasets.forEach(function (ds, di) {
                    var meta = chart.getDatasetMeta(di);
                    if (meta.hidden) return;
                    meta.data.forEach(function (el, i) {
                        var v = ds.data[i];
                        if (v === null || v === undefined) return;
                        var x = el.x;
                        var y = Math.min(el.y, el.base) - 4;
                        if (!isFinite(x) || !isFinite(y)) return;
                        ctx.fillText(formatNum(v), x, y);
                    });
                });
                ctx.restore();
            },
        };

        canvas._srChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: prevLabel,
                        data: prev,
                        backgroundColor: colorPrev,
                        hoverBackgroundColor: '#f0b56e',
                        borderRadius: 3,
                        borderSkipped: false,
                        maxBarThickness: 56,
                    },
                    {
                        label: curLabel,
                        data: cur,
                        backgroundColor: colorCur,
                        hoverBackgroundColor: '#5fc4b8',
                        borderRadius: 3,
                        borderSkipped: false,
                        maxBarThickness: 56,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { weight: '600', size: 13 },
                        bodyFont: { size: 13 },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true,
                        boxPadding: 4,
                        callbacks: {
                            title: function (items) {
                                return (items[0] && items[0].label) || '';
                            },
                            afterBody: function (items) {
                                if (!items.length) return '';
                                var idx = items[0].dataIndex;
                                var d = deltas[idx];
                                if (d === null || d === undefined) return '';
                                var sign = d > 0 ? '+' : '';
                                return sign + String(Math.round(d * 10) / 10).replace('.', ',') + '%';
                            },
                            label: function (item) {
                                return ' ' + item.dataset.label + ': ' + formatNum(item.raw);
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#334155',
                            font: { size: 13, weight: '600' },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grace: '18%',
                        grid: {
                            color: '#e8edf3',
                            drawBorder: false,
                        },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' },
                            callback: function (v) {
                                if (v >= 1000) {
                                    return formatNum(Math.round(v / 1000)) + 'K';
                                }
                                return formatNum(v);
                            },
                        },
                    },
                },
            },
            plugins: [valueLabelsPlugin],
        });

        // Страховка: после layout пересчитать (иначе иногда столбцы нулевой высоты)
        requestAnimationFrame(function () {
            if (canvas._srChart) {
                canvas._srChart.resize();
                canvas._srChart.update('none');
            }
        });
    }

    function refreshCompareCharts() {
        document.querySelectorAll('[data-sr-compare-chart] canvas').forEach(function (canvas) {
            if (!canvas._srChart) return;
            try {
                canvas._srChart.resize();
                canvas._srChart.update('none');
            } catch (e) { /* ignore */ }
        });
    }

    function initDonutTips(wrap) {
        if (!wrap || wrap._srDonutTips) return;
        var tip = wrap.querySelector('[data-sr-donut-tip]');
        if (!tip) return;
        wrap._srDonutTips = true;

        function hide() {
            tip.classList.remove('is-on');
            tip.innerHTML = '';
        }

        function show(seg) {
            var title = seg.getAttribute('data-tip-title') || '';
            var val = seg.getAttribute('data-tip-val') || '';
            tip.innerHTML = '<span class="cabinet-sr-donut__tip-title"></span><span class="cabinet-sr-donut__tip-val"></span>';
            tip.querySelector('.cabinet-sr-donut__tip-title').textContent = title;
            tip.querySelector('.cabinet-sr-donut__tip-val').textContent = val;
            tip.classList.add('is-on');
        }

        wrap.querySelectorAll('[data-sr-donut-seg]').forEach(function (seg) {
            seg.addEventListener('mouseenter', function () { show(seg); });
            seg.addEventListener('mouseleave', hide);
            seg.addEventListener('focus', function () { show(seg); });
            seg.addEventListener('blur', hide);
        });
    }

    function boot() {
        document.querySelectorAll('[data-sr-donut-chart]').forEach(initDonutTips);
        if (typeof Chart === 'undefined') return;
        document.querySelectorAll('[data-sr-day-chart] canvas').forEach(initDayCanvas);
        document.querySelectorAll('[data-sr-compare-chart]').forEach(initCompare);
        // Chart.js иногда считает layout до финальной ширины карточки — пересчёт
        requestAnimationFrame(function () {
            refreshCompareCharts();
            requestAnimationFrame(refreshCompareCharts);
        });
        window.addEventListener('load', refreshCompareCharts);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
