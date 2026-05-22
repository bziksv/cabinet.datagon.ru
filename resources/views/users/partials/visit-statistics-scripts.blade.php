<script>
    (function () {
        var userId = {{ (int) $user->id }};
        var baseUrl = @json(route('visit.statistics', $user->id));
        var activeDates = @json($activeDates);
        var lineChart = null;
        var doughnutChart = null;
        var modulesTable = null;

        function markCalendarDays(picker) {
            if (!picker || !picker.container) {
                return;
            }
            var showDates = [];
            picker.container.find('.drp-calendar.left td, .drp-calendar.right td').each(function () {
                var $td = $(this);
                if ($td.hasClass('off') || !$td.hasClass('available')) {
                    return;
                }
                var title = $td.attr('data-title');
                if (!title) {
                    return;
                }
                var m = moment(title, 'YYYY-MM-DD');
                if (m.isValid()) {
                    showDates.push({date: m.format('YYYY-MM-DD'), el: $td});
                }
            });
            activeDates.forEach(function (item) {
                var found = showDates.find(function (d) { return d.date === item.date; });
                if (found) {
                    found.el.addClass('exist-position');
                }
            });
        }

        function navigatePeriod(fromIso, toIso) {
            window.location.href = baseUrl + '?from=' + encodeURIComponent(fromIso) + '&to=' + encodeURIComponent(toIso);
        }

        var $range = $('#cabinet-uvs-date-range');
        if ($range.length && typeof moment !== 'undefined' && $.fn.daterangepicker) {
            $range.daterangepicker({
                opens: 'left',
                locale: {
                    format: 'DD-MM-YYYY',
                    firstDay: 1,
                    daysOfWeek: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                    applyLabel: @json(__('Apply')),
                    cancelLabel: @json(__('Cancel')),
                },
                ranges: {
                    [@json(__('Last 7 days'))]: [moment().subtract(6, 'days'), moment()],
                    [@json(__('Last 30 days'))]: [moment().subtract(29, 'days'), moment()],
                    [@json(__('Last 60 days'))]: [moment().subtract(59, 'days'), moment()],
                },
                alwaysShowCalendars: true,
                showCustomRangeLabel: false,
            });

            $range.on('showCalendar.daterangepicker apply.daterangepicker', function (ev, picker) {
                markCalendarDays(picker);
            });
        }

        $('#cabinet-uvs-apply').on('click', function () {
            var picker = $range.data('daterangepicker');
            if (!picker) {
                return;
            }
            navigatePeriod(picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'));
        });

        $('.cabinet-uvs-preset').on('click', function () {
            var days = parseInt($(this).data('days'), 10);
            navigatePeriod(
                moment().subtract(days - 1, 'days').format('YYYY-MM-DD'),
                moment().format('YYYY-MM-DD')
            );
        });

        function readJson(id) {
            var el = document.getElementById(id);
            if (!el) {
                return null;
            }
            try {
                return JSON.parse(el.textContent);
            } catch (e) {
                return null;
            }
        }

        function initCharts() {
            if (typeof Chart === 'undefined') {
                return;
            }

            var chartData = readJson('cabinet-uvs-chart-data');
            var doughnutData = readJson('cabinet-uvs-doughnut-data');

            if (lineChart) {
                lineChart.destroy();
                lineChart = null;
            }
            if (doughnutChart) {
                doughnutChart.destroy();
                doughnutChart = null;
            }

            var lineCanvas = document.getElementById('cabinet-uvs-line-chart');
            if (lineCanvas && chartData && chartData.labels && chartData.labels.length) {
                lineChart = new Chart(lineCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [
                            {
                                label: @json(__('Actions')),
                                data: chartData.actions,
                                borderColor: '#0d6efd',
                                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                                tension: 0.3,
                                yAxisID: 'y',
                            },
                            {
                                label: @json(__('Refreshes')),
                                data: chartData.refresh,
                                borderColor: '#198754',
                                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                                tension: 0.3,
                                yAxisID: 'y',
                            },
                            {
                                label: @json(__('Time (min)')),
                                data: chartData.minutes,
                                borderColor: '#fd7e14',
                                backgroundColor: 'rgba(253, 126, 20, 0.1)',
                                tension: 0.3,
                                yAxisID: 'y1',
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {mode: 'index', intersect: false},
                        plugins: {legend: {position: 'bottom'}},
                        scales: {
                            x: {grid: {display: false}},
                            y: {beginAtZero: true, position: 'left', ticks: {precision: 0}},
                            y1: {beginAtZero: true, position: 'right', grid: {drawOnChartArea: false}},
                        },
                    },
                });
            }

            var doughnutCanvas = document.getElementById('cabinet-uvs-doughnut-chart');
            if (doughnutCanvas && doughnutData && doughnutData.values && doughnutData.values.length) {
                doughnutChart = new Chart(doughnutCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: doughnutData.labels,
                        datasets: [{
                            data: doughnutData.values,
                            backgroundColor: doughnutData.colors,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {legend: {position: 'bottom'}},
                    },
                });
            }
        }

        function initModulesTable() {
            var $table = $('#cabinet-uvs-modules-table');
            if (!$table.length) {
                return;
            }
            if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
                $table.DataTable().destroy();
            }
            if ($.fn.DataTable) {
                modulesTable = $table.DataTable({
                    order: [[4, 'desc']],
                    pageLength: 25,
                    lengthMenu: [10, 25, 50, 100],
                    language: {
                        lengthMenu: '_MENU_',
                        search: '',
                        searchPlaceholder: @json(__('Search')),
                        paginate: {previous: '‹', next: '›'},
                    },
                    columnDefs: [{orderable: false, targets: [6]}],
                });
            }
        }

        initCharts();
        initModulesTable();
    })();
</script>
