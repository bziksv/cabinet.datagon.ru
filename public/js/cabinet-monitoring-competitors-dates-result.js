(function ($, cfg) {
    'use strict';

    if (!$ || !cfg || !cfg.resultData) {
        return;
    }

    var chart;
    var parsed;
    var currentMetric = 'top_10';
    var currentView = 'both';
    var hiddenDomains = {};
    var palette = [
        '#0284c7', '#dc2626', '#16a34a', '#9333ea', '#ea580c', '#0891b2',
        '#be185d', '#854d0e', '#4338ca', '#0f766e', '#b91c1c', '#4d7c0f',
    ];

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function parseData(raw) {
        var dates = Object.keys(raw || {}).sort();
        var domainSet = {};

        dates.forEach(function (date) {
            Object.keys(raw[date] || {}).forEach(function (domain) {
                domainSet[domain] = true;
            });
        });

        var domains = Object.keys(domainSet);
        if (cfg.ownDomain) {
            domains.sort(function (a, b) {
                if (a === cfg.ownDomain) {
                    return -1;
                }
                if (b === cfg.ownDomain) {
                    return 1;
                }
                return a.localeCompare(b);
            });
        } else {
            domains.sort();
        }

        return {
            datesAsc: dates,
            datesDesc: dates.slice().reverse(),
            domains: domains,
            raw: raw,
        };
    }

    function formatDate(iso) {
        var parts = String(iso).split('-');
        if (parts.length === 3 && parts[0].length === 4) {
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }

        parts = String(iso).split('-');
        if (parts.length === 3) {
            return parts[0] + '.' + parts[1] + '.' + parts[2];
        }

        return iso;
    }

    function metricMeta(metric) {
        return cfg.metrics[metric] || { label: metric, higherBetter: true };
    }

    function metricValue(date, domain, metric) {
        if (!parsed.raw[date] || !parsed.raw[date][domain]) {
            return null;
        }

        var val = parsed.raw[date][domain][metric];
        return val === undefined || val === null ? null : Number(val);
    }

    function findLeaderIndex(values, higherBetter) {
        var leader = null;
        var leaderVal = null;

        values.forEach(function (val, index) {
            if (val === null || isNaN(val)) {
                return;
            }
            if (leaderVal === null) {
                leaderVal = val;
                leader = index;
                return;
            }
            if (higherBetter ? val > leaderVal : val < leaderVal) {
                leaderVal = val;
                leader = index;
            }
        });

        return leader;
    }

    function formatValue(val, metric) {
        if (val === null || isNaN(val)) {
            return '—';
        }

        if (metric === 'avg') {
            return String(Math.round(val * 100) / 100);
        }

        return String(Math.round(val * 100) / 100);
    }

    function isDomainHidden(domain) {
        return !!hiddenDomains[domain];
    }

    function renderLegend() {
        var html = '';
        parsed.domains.forEach(function (domain, index) {
            var isOwn = domain === cfg.ownDomain;
            var hidden = isDomainHidden(domain);
            html += '<button type="button"'
                + ' class="cabinet-mon-dates-result-legend__item'
                + (isOwn ? ' is-own' : '')
                + (hidden ? ' is-off' : '')
                + '"'
                + ' data-domain="' + escapeHtml(domain) + '"'
                + ' aria-pressed="' + (hidden ? 'false' : 'true') + '"'
                + ' title="' + escapeHtml(domain) + '">';
            html += '<span class="cabinet-mon-dates-result-legend__swatch" style="background:' + palette[index % palette.length] + '"></span>';
            html += '<span class="cabinet-mon-dates-result-legend__label">' + escapeHtml(domain);
            if (isOwn) {
                html += ' <span class="badge rounded-pill text-bg-info">' + escapeHtml(cfg.i18n.yourSite) + '</span>';
            }
            html += '</span></button>';
        });
        $('#dates-result-legend').html(html);
    }

    function syncChartVisibility() {
        if (!chart) {
            return;
        }

        parsed.domains.forEach(function (domain, index) {
            var meta = chart.getDatasetMeta(index);
            if (meta) {
                meta.hidden = isDomainHidden(domain);
            }
        });
        chart.update();
    }

    function updateLegendState() {
        $('#dates-result-legend .cabinet-mon-dates-result-legend__item').each(function () {
            var domain = $(this).attr('data-domain');
            var hidden = isDomainHidden(domain);
            $(this)
                .toggleClass('is-off', hidden)
                .attr('aria-pressed', hidden ? 'false' : 'true');
        });
    }

    function renderChart(metric) {
        var meta = metricMeta(metric);
        var $canvas = $('#dates-result-chart');

        if (!$canvas.length || typeof Chart === 'undefined') {
            return;
        }

        if (chart) {
            chart.destroy();
        }

        var labels = parsed.datesAsc.map(formatDate);
        var datasets = parsed.domains.map(function (domain, index) {
            var isOwn = domain === cfg.ownDomain;
            var color = palette[index % palette.length];

            return {
                label: domain,
                data: parsed.datesAsc.map(function (date) {
                    return metricValue(date, domain, metric);
                }),
                borderColor: color,
                backgroundColor: color,
                fill: false,
                lineTension: 0.15,
                borderWidth: isOwn ? 3 : 1.5,
                pointRadius: isOwn ? 4 : 2,
                pointHoverRadius: isOwn ? 5 : 3,
                spanGaps: true,
                hidden: isDomainHidden(domain),
            };
        });

        chart = new Chart($canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets,
            },
            options: {
                maintainAspectRatio: false,
                legend: {
                    display: false,
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    filter: function (tooltipItem, data) {
                        var dataset = data.datasets[tooltipItem.datasetIndex];
                        if (dataset && dataset.hidden) {
                            return false;
                        }
                        if (chart) {
                            var meta = chart.getDatasetMeta(tooltipItem.datasetIndex);
                            if (meta && meta.hidden) {
                                return false;
                            }
                        }
                        return true;
                    },
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: metric !== 'avg',
                            reverse: metric === 'avg',
                        },
                        scaleLabel: {
                            display: true,
                            labelString: metric === 'avg' ? cfg.i18n.chartYAvg : cfg.i18n.chartYTop,
                        },
                    }],
                    xAxes: [{
                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 12,
                        },
                    }],
                },
            },
        });

        $('#dates-result-chart-title').text(meta.label);
    }

    function renderTable(metric) {
        var meta = metricMeta(metric);
        var html = '<thead><tr><th scope="col" class="cabinet-mon-dates-result-table__date">' + escapeHtml(cfg.i18n.date) + '</th>';

        parsed.domains.forEach(function (domain) {
            var isOwn = domain === cfg.ownDomain;
            html += '<th scope="col" class="cabinet-mon-dates-result-table__domain' + (isOwn ? ' is-own' : '') + '">';
            html += escapeHtml(domain);
            if (isOwn) {
                html += ' <span class="badge rounded-pill text-bg-info cabinet-mon-dates-result-own-badge">' + escapeHtml(cfg.i18n.yourSite) + '</span>';
            }
            html += '</th>';
        });
        html += '</tr></thead><tbody>';

        parsed.datesDesc.forEach(function (date, rowIndex) {
            var rowValues = parsed.domains.map(function (domain) {
                return metricValue(date, domain, metric);
            });
            var leaderIndex = findLeaderIndex(rowValues, meta.higherBetter);

            html += '<tr><th scope="row" class="cabinet-mon-dates-result-table__date">' + formatDate(date) + '</th>';

            parsed.domains.forEach(function (domain, colIndex) {
                var val = metricValue(date, domain, metric);
                var classes = ['cabinet-mon-dates-result-table__val'];
                if (colIndex === leaderIndex) {
                    classes.push('is-leader');
                }
                if (domain === cfg.ownDomain) {
                    classes.push('is-own');
                }

                var deltaHtml = '';
                var prevDate = parsed.datesDesc[rowIndex + 1];
                if (prevDate) {
                    var prev = metricValue(prevDate, domain, metric);
                    if (prev !== null && val !== null) {
                        var diff = Math.round((val - prev) * 100) / 100;
                        if (diff !== 0) {
                            var good = meta.higherBetter ? diff > 0 : diff < 0;
                            deltaHtml = '<span class="cabinet-mon-dates-result-delta ' + (good ? 'is-good' : 'is-bad') + '">'
                                + (diff > 0 ? '+' : '') + diff + '</span>';
                        }
                    }
                }

                html += '<td class="' + classes.join(' ') + '"'
                    + (colIndex === leaderIndex ? ' title="' + escapeHtml(cfg.i18n.leader) + '"' : '')
                    + '><span class="cabinet-mon-dates-result-cell__main">' + formatValue(val, metric) + '</span>'
                    + deltaHtml + '</td>';
            });

            html += '</tr>';
        });

        html += '</tbody>';
        $('#dates-result-table').html(html);
    }

    function applyView() {
        var showChart = currentView === 'both' || currentView === 'chart';
        var showTable = currentView === 'both' || currentView === 'table';

        $('#dates-result-chart-section').toggleClass('d-none', !showChart);
        $('#dates-result-table-section').toggleClass('d-none', !showTable);

        if (showChart) {
            renderChart(currentMetric);
        } else if (chart) {
            chart.destroy();
            chart = null;
        }

        if (showTable) {
            renderTable(currentMetric);
        }
    }

    function bindControls() {
        $('#dates-result-metric').on('click', 'button[data-metric]', function () {
            var metric = $(this).attr('data-metric');
            if (!metric || metric === currentMetric) {
                return;
            }
            currentMetric = metric;
            $('#dates-result-metric button').removeClass('active');
            $(this).addClass('active');
            applyView();
        });

        $('#dates-result-view').on('click', 'button[data-view]', function () {
            var view = $(this).attr('data-view');
            if (!view || view === currentView) {
                return;
            }
            currentView = view;
            $('#dates-result-view button').removeClass('active');
            $(this).addClass('active');
            applyView();
        });

        $('#dates-result-legend').on('click', '.cabinet-mon-dates-result-legend__item', function () {
            var domain = $(this).attr('data-domain');
            if (!domain) {
                return;
            }
            hiddenDomains[domain] = !hiddenDomains[domain];
            syncChartVisibility();
            updateLegendState();
        });
    }

    parsed = parseData(cfg.resultData);
    if (parsed.datesAsc.length === 0) {
        return;
    }

    renderLegend();
    bindControls();
    applyView();
}(window.jQuery, window.cabinetMonDatesResultConfig));
