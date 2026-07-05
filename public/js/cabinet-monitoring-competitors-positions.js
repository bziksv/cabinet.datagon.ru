(function ($, cfg) {
    'use strict';

    if (!$ || !cfg) {
        return;
    }

    var table;
    var chartAvg;
    var chart3;
    var chart10;
    var chart30;
    var chart50;
    var chart100;
    var isTableLoading = false;
    var positionsLoaded = false;
    var positionsLoading = false;
    var activeSnapshot = cfg.snapshot || null;

    function dataTableLanguage(overrides) {
        var lang = {
            lengthMenu: '_MENU_',
            search: '_INPUT_',
            searchPlaceholder: cfg.i18n.search,
            paginate: {
                first: '«',
                last: '»',
                next: '»',
                previous: '«',
            },
            info: cfg.i18n.tableInfo,
            infoEmpty: cfg.i18n.tableInfoEmpty,
            infoFiltered: cfg.i18n.tableInfoFiltered,
            emptyTable: cfg.i18n.empty,
        };

        if (overrides) {
            $.extend(lang, overrides);
        }

        return lang;
    }

    function wirePositionsDataTableBar(api) {
        var $wrapper = $(api.table().container());
        $wrapper.find('.dataTables_length').appendTo('#comp-positions-dt-length');
        $wrapper.find('.dataTables_filter').appendTo('#comp-positions-dt-filter');
    }

    function showToast(message) {
        var $container = $('#toast-container');
        $container.find('.toast-message').text(message);
        $container.removeClass('d-none');
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            bootstrap.Toast.getOrCreateInstance($container.find('.toast')[0]).show();
        }
        setTimeout(function () {
            $container.addClass('d-none');
        }, 5000);
    }

    function renderTableBody(dt, data, shouldDraw) {
        var trs = [];
        $.each(data, function (query, info) {
            var tr = [query];
            $.each(cfg.competitors, function (i, site) {
                var val = info && info[site] !== undefined ? info[site] : 0;
                tr.push(val === 0 ? 101 : val);
            });
            trs.push(tr);
        });
        if (!trs.length) {
            return;
        }
        dt.rows.add(trs);
        if (shouldDraw !== false) {
            dt.draw(false);
        }
    }

    function colorCells() {
        $('.cabinet-mon-comp-positions-table .min-value').removeClass('min-value');

        $('#table tbody tr').each(function () {
            var $cells = $(this).find('td');
            if ($cells.length <= 2) {
                return;
            }

            var array = [];
            $cells.each(function (cellIndex) {
                if (cellIndex === 0) {
                    return;
                }
                var cellVal = parseFloat($(this).text());
                if (!isNaN(cellVal) && cellVal !== 0) {
                    array.push({
                        cellIndex: cellIndex + 1,
                        cellVal: cellVal,
                    });
                }
            });

            if (array.length > 0) {
                array.sort(function (prev, next) {
                    return prev.cellVal - next.cellVal;
                });
                $cells.eq(array[0].cellIndex - 1).addClass('min-value');
            }
        });
    }

    function renderChartTable(tableId, body, data, key, sortType) {
        if ($.fn.DataTable.isDataTable($(tableId))) {
            $(tableId).DataTable().destroy();
            $(tableId + ' .render-more').remove();
        }

        var rows = '';
        $.each(data, function (domain, values) {
            rows += '<tr class="render-more">';
            rows += '<td>' + domain + '</td>';
            rows += '<td>' + String(values[key]).substring(0, 5) + '</td></tr>';
        });
        $(body).html(rows);

        $(tableId).DataTable({
            order: [[1, sortType || 'desc']],
            lengthMenu: [10, 25, 50, 100],
            pageLength: 10,
            language: dataTableLanguage(),
            initComplete: function () {
                if (window.cabinetMonitoringSearch) {
                    window.cabinetMonitoringSearch.dataTableInitComplete.call(this);
                }
            },
        });
    }

    function renderChart(labels, colors, data, target, label) {
        return new Chart($(target), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                }],
            },
            options: {
                title: {
                    display: true,
                    text: label,
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            max: 100,
                            fontSize: 12,
                        },
                    }],
                    xAxes: [{
                        ticks: {
                            maxRotation: 60,
                            minRotation: 60,
                            fontSize: 12,
                        },
                    }],
                },
                legend: {
                    display: false,
                },
                maintainAspectRatio: false,
            },
        });
    }

    function getColorArray() {
        var colorArray = [
            'rgba(220, 51, 10, 0.6)', 'rgb(203,60,25)', 'rgba(121, 25, 6, 1)',
            'rgba(214, 96, 110, 0.6)', 'rgba(214, 96, 110, 1)', 'rgba(252, 170, 153, 0.6)',
            'rgba(252, 170, 153, 1)', 'rgba(214, 2, 86, 0.6)', 'rgba(214, 2, 86, 1)',
            'rgba(147,50,88, 1)', 'rgba(247, 220, 163, 1)', 'rgba(204, 118, 32, 0.6)',
            'rgba(204, 118, 32, 1)', 'rgba(255,89,0,0.6)', 'rgba(255, 89, 0, 1)',
            'rgba(164, 58 ,1, 1)', 'rgba(73, 28, 1, 0.6)', 'rgba(178, 135, 33, 0.6)',
            'rgba(178, 135, 33, 1)', 'rgba(246, 223, 78, 1)', 'rgba(1, 253, 215, 0.6)',
            'rgba(1, 253, 215, 1)', 'rgba(1, 148, 130, 0.6)', 'rgba(1, 79, 66, 0.6)',
            'rgba(139, 150, 24, 0.6)', 'rgba(154, 205, 50, 0.6)', 'rgba(154, 205, 50, 1)',
            'rgb(17, 255, 0)', 'rgba(151, 186, 229, 1)', 'rgba(0, 69, 255, 0.6)',
            'rgba(0, 69, 255, 1)', 'rgba(1, 45, 152, 0.6)', 'rgba(157, 149, 226, 1)',
            'rgba(6, 136, 165, 0.6)', 'rgba(64, 97, 206, 1)', 'rgba(19,212,224, 0.6)',
            'rgba(19,212,224, 1)', 'rgba(2, 97, 214, 0.6)', 'rgba(159, 112, 216, 0.6)',
            'rgba(239, 50, 223, 0.6)', 'rgba(239, 50, 223, 1)', 'rgba(209, 46, 127, 0.6)',
            'rgba(209, 46, 127, 1)', 'rgba(194, 85, 237, 1)', 'rgba(252, 194, 243, 1)',
            'rgba(244, 139, 200, 0.6)', 'rgba(244, 139, 200, 1)', 'rgba(87, 64, 64, 0.6)',
            'rgba(239, 211, 211, 0.6)', 'rgba(163, 209, 234, 0.6)', 'rgba(234,163,163,0.6)',
            'rgba(232,194,90,0.6)',
        ];

        return colorArray.sort(function () {
            return Math.random() - 0.5;
        });
    }

    function renderCharts(data, destroy) {
        var colorArray = getColorArray();
        var labels = [];
        var avg = [];
        var reverseDatas = [];
        var top3 = [];
        var top10 = [];
        var top30 = [];
        var top50 = [];
        var top100 = [];
        var colors = [];

        $.each(data, function (domain, info) {
            if (domain !== '') {
                labels.push(domain);
                avg.push(info.avg);
                reverseDatas.push(100 - info.avg);
                colors.push(colorArray.shift());
                top3.push(info.top_3);
                top10.push(info.top_10);
                top30.push(info.top_30);
                top50.push(info.top_50);
                top100.push(info.top_100);
            }
        });

        if (destroy) {
            chartAvg.destroy();
            chart3.destroy();
            chart10.destroy();
            if (chart30) {
                chart30.destroy();
            }
            if (chart50) {
                chart50.destroy();
            }
            if (chart100) {
                chart100.destroy();
            }
        }

        chartAvg = new Chart($('#bar-chart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { data: avg, backgroundColor: 'transparent' },
                    { data: reverseDatas, backgroundColor: colors },
                ],
            },
            options: {
                title: {
                    display: true,
                    text: cfg.i18n.avgPosition,
                },
                scales: {
                    xAxes: [{
                        stacked: true,
                        ticks: { maxRotation: 60, minRotation: 60, fontSize: 12 },
                    }],
                    yAxes: [{
                        stacked: true,
                        ticks: {
                            reverse: true,
                            beginAtZero: true,
                            stepSize: 10,
                            max: 100,
                            min: 0,
                        },
                    }],
                },
                tooltips: {
                    callbacks: {
                        title: function (item) {
                            return item[0].xLabel;
                        },
                        label: function (item) {
                            if (item.datasetIndex === 1) {
                                return cfg.i18n.avgPosition + ' ' + String(100 - item.yLabel).substring(0, 5);
                            }
                            return cfg.i18n.raiseNeeded + ' ' + (100 - item.yLabel);
                        },
                    },
                },
                legend: { display: false },
                maintainAspectRatio: false,
            },
        });

        chart3 = renderChart(labels, colors, top3, '#bar-chart-3', cfg.i18n.topPct + ' 3');
        chart10 = renderChart(labels, colors, top10, '#bar-chart-10', cfg.i18n.topPct + ' 10');
        chart30 = renderChart(labels, colors, top30, '#bar-chart-30', cfg.i18n.topPct + ' 30');
        chart50 = renderChart(labels, colors, top50, '#bar-chart-50', cfg.i18n.topPct + ' 50');
        chart100 = renderChart(labels, colors, top100, '#bar-chart-100', cfg.i18n.topPct + ' 100');
    }

    function calculateAvgValues(array) {
        var domains = [];
        var results = {};

        $.each(array, function (key, values) {
            $.each(values, function (domain) {
                domains.push(domain);
            });
            return false;
        });

        $.each(domains, function (k, v) {
            results[v] = { avg: 0, top_3: 0, top_10: 0, top_30: 0, top_50: 0, top_100: 0, sum: 0 };
        });

        for (var i = 0; i < array.length; i++) {
            $.each(domains, function (k, v) {
                results[v].sum += array[i][v].sum;
                results[v].top_3 += array[i][v].top_3;
                results[v].top_10 += array[i][v].top_10;
                results[v].top_30 += array[i][v].top_30;
                results[v].top_50 += array[i][v].top_50;
                results[v].top_100 += array[i][v].top_100;
            });
        }

        $.each(results, function (k, v) {
            results[k].avg = results[k].sum / Number(cfg.totalWords);
            results[k].top_3 = (results[k].top_3 / Number(cfg.totalWords)) * 100;
            results[k].top_10 = (results[k].top_10 / Number(cfg.totalWords)) * 100;
            results[k].top_30 = (results[k].top_30 / Number(cfg.totalWords)) * 100;
            results[k].top_50 = (results[k].top_50 / Number(cfg.totalWords)) * 100;
            results[k].top_100 = (results[k].top_100 / Number(cfg.totalWords)) * 100;
        });

        return results;
    }

    function renderStatistics(data, destroy) {
        $('#statistics-table').removeClass('d-none');
        $('#comp-positions-table-title').removeClass('d-none');

        window.requestAnimationFrame(function () {
            renderChartTable('#avg-position', '#avg-position-tbody', data, 'avg', 'asc');
            renderChartTable('#top3', '#top3-tbody', data, 'top_3');
            renderChartTable('#top10', '#top10-tbody', data, 'top_10');
            renderChartTable('#top30', '#top30-tbody', data, 'top_30');
            renderChartTable('#top50', '#top50-tbody', data, 'top_50');
            renderChartTable('#top100', '#top100-tbody', data, 'top_100');

            window.setTimeout(function () {
                renderCharts(data, destroy);
            }, 60);
        });
    }

    function fetchSnapshot(regionId) {
        if (!cfg.routes.competitorsSnapshot) {
            return $.Deferred().resolve(activeSnapshot).promise();
        }

        return $.ajax({
            type: 'POST',
            dataType: 'json',
            url: cfg.routes.competitorsSnapshot,
            data: {
                _token: cfg.csrf,
                projectId: cfg.projectId,
                region: regionId,
            },
        }).then(function (response) {
            activeSnapshot = response.snapshot || null;
            cfg.snapshot = activeSnapshot;
            return activeSnapshot;
        });
    }

    function ensureSnapshot(regionId) {
        if (activeSnapshot && activeSnapshot.dateOnly && activeSnapshot.lr) {
            return $.Deferred().resolve(activeSnapshot).promise();
        }

        return fetchSnapshot(regionId);
    }

    function flattenKeywords(keywords) {
        return normalizeBatches(keywords).reduce(function (acc, batch) {
            return acc.concat(batch);
        }, []);
    }

    function beginLoadingUi(mode) {
        positionsLoading = true;
        $('#comp-positions-idle').addClass('d-none');
        $('#comp-positions-load').prop('disabled', true);
        $('#download-results').removeClass('d-none');
        $('#comp-positions-table-area').addClass('d-none');
        $('#comp-positions-table-title').addClass('d-none');
        $('#statistics-table').addClass('d-none');

        if (mode === 'bulk') {
            $('#comp-positions-progress-percent-wrap').addClass('d-none');
            $('#comp-positions-progress-detail').text(
                Number(cfg.totalWords).toLocaleString('ru-RU') + ' ' + (cfg.i18n.loadingQueries || '')
            );
        } else {
            $('#comp-positions-progress-percent-wrap').removeClass('d-none');
            $('#comp-positions-progress-detail').text('');
            $('#ready-percent').text('0');
        }
    }

    function finishLoadingUi(dt) {
        isTableLoading = false;
        positionsLoading = false;
        positionsLoaded = true;
        dt.draw(false);
        colorCells();
        $('#download-results').addClass('d-none');
        $('#comp-positions-table-area').removeClass('d-none');
        $('#comp-positions-load').prop('disabled', false);
    }

    function loadPositionsTable(reload) {
        if (positionsLoading) {
            return;
        }

        renderInfo(!!reload);
    }

    function statisticsPayload(extra) {
        var payload = {
            _token: cfg.csrf,
            competitors: cfg.competitors,
            region: $('#searchEngines').val(),
            projectId: cfg.projectId,
        };

        if (activeSnapshot && activeSnapshot.dateOnly && activeSnapshot.lr) {
            payload.dateOnly = activeSnapshot.dateOnly;
            payload.lr = activeSnapshot.lr;
        }

        return $.extend(payload, extra || {});
    }

    function loadBulkStatistics(dt, destroy) {
        beginLoadingUi('bulk');
        isTableLoading = true;

        if (destroy && table) {
            table.clear();
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            timeout: 300000,
            url: cfg.routes.getStatistics,
            data: statisticsPayload({ bulk: 1 }),
        }).done(function (response) {
            if (response.snapshot && (!activeSnapshot || !activeSnapshot.dateOnly)) {
                activeSnapshot = response.snapshot;
                cfg.snapshot = activeSnapshot;
            }
            renderTableBody(dt, response.visibility, false);
            finishLoadingUi(dt);
            renderStatistics(response.statistics, destroy);
        }).fail(function () {
            sendRequests(cfg.keywords, dt, destroy);
        });
    }

    function normalizeBatches(allWords) {
        if (Array.isArray(allWords)) {
            return allWords;
        }

        return Object.keys(allWords).map(function (key) {
            return allWords[key];
        });
    }

    function sendRequests(allWords, dt, destroy) {
        var batches = normalizeBatches(allWords);
        var parallel = Math.max(1, Number(cfg.parallel) || 5);
        var countReadyWords = 0;
        var retryBatches = [];
        var statsChunks = new Array(batches.length);
        var queue = batches.map(function (words, index) {
            return { words: words, index: index };
        });
        var inFlight = 0;

        beginLoadingUi('batch');
        isTableLoading = true;

        function updateProgress() {
            $('#ready-percent').text(Number(countReadyWords / cfg.totalWords * 100).toFixed());
        }

        function finishPass() {
            if (retryBatches.length > 0) {
                showToast(cfg.i18n.dataRetry);
                sendRequests(retryBatches, dt, destroy);
                return;
            }

            isTableLoading = false;
            finishLoadingUi(dt);
            renderStatistics(calculateAvgValues(statsChunks.filter(function (chunk) {
                return !!chunk;
            })), destroy);
        }

        function pump() {
            while (inFlight < parallel && queue.length > 0) {
                var item = queue.shift();
                inFlight++;

                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    timeout: 120000,
                    url: cfg.routes.getStatistics,
                    data: statisticsPayload({ keywords: item.words }),
                }).done(function (response) {
                    if (response.snapshot && (!activeSnapshot || !activeSnapshot.dateOnly)) {
                        activeSnapshot = response.snapshot;
                        cfg.snapshot = activeSnapshot;
                    }
                    renderTableBody(dt, response.visibility, false);
                    countReadyWords += item.words.length;
                    statsChunks[item.index] = response.statistics;
                    updateProgress();
                }).fail(function () {
                    retryBatches.push(item.words);
                }).always(function () {
                    inFlight--;
                    if (queue.length > 0) {
                        pump();
                    } else if (inFlight === 0) {
                        finishPass();
                    }
                });
            }
        }

        if (!batches.length) {
            finishPass();
            return;
        }

        pump();
    }

    function renderInfo(destroy) {
        if (destroy && table) {
            table.clear().draw();
        } else if (!table) {
            table = $('#table').DataTable({
                ordering: false,
                lengthMenu: [10, 25, 50, 100],
                pageLength: 50,
                dom: 'lfrt<"cabinet-mon-comp-positions-dt-footer"ip>',
                autoWidth: false,
                language: dataTableLanguage(),
                drawCallback: function () {
                    if (!isTableLoading) {
                        colorCells();
                    }
                },
                initComplete: function () {
                    wirePositionsDataTableBar(this.api());
                    if (window.cabinetMonitoringSearch) {
                        window.cabinetMonitoringSearch.dataTableInitComplete.call(this);
                    }
                },
            });
        }

        ensureSnapshot($('#searchEngines').val()).always(function () {
            if (cfg.useBulkLoad) {
                loadBulkStatistics(table, destroy);
            } else {
                sendRequests(cfg.keywords, table, destroy);
            }
        });
    }

    function wireDomainColumnHover() {
        var $table = $('#table');
        var $headRow = $('#tableHeadRow');

        $headRow.off('mouseenter.colhover mouseleave.colhover', 'th[data-col-domain]');
        $headRow.on('mouseenter.colhover', 'th[data-col-domain]', function () {
            var index = $(this).index() + 1;
            $table.find('thead th:nth-child(' + index + ')').addClass('is-col-hover');
            $table.find('tbody td:nth-child(' + index + ')').addClass('is-col-hover');
        });
        $headRow.on('mouseleave.colhover', 'th[data-col-domain]', function () {
            $table.find('.is-col-hover').removeClass('is-col-hover');
        });
    }

    var pendingRemoveCompetitor = null;

    function hideRemoveCompetitorModal() {
        var modalEl = document.getElementById('removeCompetitor');
        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function checkChartState(id) {
        var avg = localStorage.getItem('lk_redbox_button_' + id) || 'false';
        if (avg === 'false') {
            $("button[data-bs-target='" + id + "']").trigger('click');
        }
    }

    $(function () {
        var filter = localStorage.getItem('lr_redbox_monitoring_selected_filter');
        if (filter !== null) {
            filter = JSON.parse(filter);
            $('#searchEngines option[value="' + filter.val + '"]').prop('selected', true);
        }

        $(document).on('click', '.remove-competitor-trigger', function () {
            pendingRemoveCompetitor = {
                columnIndex: $(this).attr('data-id'),
                url: $(this).attr('data-target'),
            };
            $('#competitor-name').text(pendingRemoveCompetitor.url);
        });

        $('#remove-competitor-confirm').on('click', function () {
            if (!pendingRemoveCompetitor || !table) {
                return;
            }

            var payload = pendingRemoveCompetitor;
            pendingRemoveCompetitor = null;

            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: cfg.routes.removeCompetitor,
                data: {
                    _token: cfg.csrf,
                    url: payload.url,
                    projectId: cfg.projectId,
                },
                success: function () {
                    table.column(payload.columnIndex).visible(false);
                    hideRemoveCompetitorModal();
                },
                error: function () {
                    pendingRemoveCompetitor = payload;
                },
            });
        });

        $('#comp-positions-load').on('click', function () {
            loadPositionsTable(positionsLoaded);
        });

        wireDomainColumnHover();

        $('#searchEngines').on('change', function () {
            localStorage.setItem('lr_redbox_monitoring_selected_filter', JSON.stringify({ val: $(this).val() }));
            activeSnapshot = null;
            cfg.snapshot = null;
            if (positionsLoaded) {
                loadPositionsTable(true);
            }
        });

        checkChartState('#avgCollapse');
        checkChartState('#top3Collapse');
        checkChartState('#top10Collapse');
        checkChartState('#top30Collapse');
        checkChartState('#top50Collapse');
        checkChartState('#top100Collapse');

        $('.chart-button').on('click', function () {
            var target = $(this).attr('data-bs-target');
            setTimeout(function () {
                localStorage.setItem('lk_redbox_button_' + target, $('.chart-button[data-bs-target="' + target + '"]').hasClass('collapsed'));
            }, 300);
        });
    });
}(window.jQuery, window.cabinetMonCompPositionsConfig));
