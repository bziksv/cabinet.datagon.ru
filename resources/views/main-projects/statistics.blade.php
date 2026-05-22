@extends('layouts.app')

@section('title', __('Module statistics') . ' — ' . __($project->title))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-module-statistics.css') }}">
    @if(is_array($buttonColumns) && count($buttonColumns) > 0)
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
    @endif
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $chart = $report['chart'];
        $statsUrl = route('main-projects.statistics', $project->id);
        $moduleColor = $project->color && preg_match('/^#[0-9A-Fa-f]{6}$/', $project->color)
            ? $project->color : '#0d6efd';
    @endphp

    <div class="cabinet-module-stats-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div class="d-flex gap-3 min-w-0">
                <span class="cabinet-ms-module-icon flex-shrink-0"
                      style="background: {{ $moduleColor }};"
                      aria-hidden="true">{!! $project->icon !!}</span>
                <div class="min-w-0">
                    <nav aria-label="breadcrumb" class="mb-2">
                        <ol class="breadcrumb mb-0 small">
                            <li class="breadcrumb-item"><a href="{{ route('main-projects.index') }}">{{ __('Menu modules') }}</a></li>
                            <li class="breadcrumb-item active">{{ __('Statistics') }}</li>
                        </ol>
                    </nav>
                    <h2 class="h4 mb-1">{{ __($project->title) }}</h2>
                    <p class="text-secondary small mb-0">
                        {{ __('Period') }}: <strong>{{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }}</strong>
                        — <strong>{{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}</strong>
                        · {{ $summary['period_days'] }} {{ __('days_abbr') }}
                    </p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ $moduleLink }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Open module') }}
                </a>
                <a href="{{ route('main-projects.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Back') }}</a>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="{{ $statsUrl }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="ms-from">{{ __('Period from') }}</label>
                        <input type="date" class="form-control form-control-sm" id="ms-from" name="from" value="{{ $dateFrom }}" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1" for="ms-to">{{ __('Period to') }}</label>
                        <input type="date" class="form-control form-control-sm" id="ms-to" name="to" value="{{ $dateTo }}" required>
                    </div>
                    <div class="col-12 col-md-6 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check-lg me-1"></i>{{ __('Apply') }}
                        </button>
                        @foreach([7 => __('7 days'), 30 => __('30 days'), 60 => __('60 days')] as $days => $label)
                            <a href="{{ $statsUrl }}?from={{ now()->subDays($days - 1)->format('Y-m-d') }}&to={{ now()->format('Y-m-d') }}"
                               class="btn btn-sm btn-outline-secondary {{ $dateFrom === now()->subDays($days - 1)->format('Y-m-d') && $dateTo === now()->format('Y-m-d') ? 'active' : '' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </form>
            </div>
        </div>

        @if(!$report['has_data'])
            <div class="alert alert-light border text-center py-5">
                <i class="bi bi-graph-down text-secondary display-6 d-block mb-2"></i>
                <p class="mb-2">{{ __('No visit data for the selected period.') }}</p>
                <p class="text-secondary small mb-0">{{ __('Check that the module has a controller for statistics and users have visit tracking enabled.') }}</p>
            </div>
        @else
            @if(!empty($report['peak_day']))
                <div class="alert alert-info py-2 mb-3 d-flex flex-wrap align-items-center gap-2">
                    <i class="bi bi-lightning-charge"></i>
                    <span>
                        {{ __('Peak activity') }}:
                        <strong>{{ $report['peak_day']['date_label'] }}</strong>
                        — {{ number_format($report['peak_day']['total'], 0, ',', ' ') }} {{ __('events') }}
                        ({{ $report['peak_day']['users_count'] }} {{ __('users') }})
                    </span>
                </div>
            @endif

            <div class="row g-3 mb-3 cabinet-ms-kpi">
                <div class="col-6 col-lg-3 d-flex">
                    <div class="info-box mb-0 flex-fill">
                        <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-people"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __('Unique users') }}</span>
                            <span class="info-box-number">{{ $summary['unique_users'] }}</span>
                            <span class="info-box-text small">{{ $summary['active_days'] }}/{{ $summary['period_days'] }} {{ __('active days') }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 d-flex">
                    <div class="info-box mb-0 flex-fill">
                        <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-hand-index"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __('Actions') }}</span>
                            <span class="info-box-number">{{ number_format($summary['total_actions'], 0, ',', ' ') }}</span>
                            @include('main-projects.partials.trend-badge', ['trend' => $summary['trend_actions']])
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 d-flex">
                    <div class="info-box mb-0 flex-fill">
                        <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-arrow-repeat"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __('Page refreshes') }}</span>
                            <span class="info-box-number">{{ number_format($summary['total_refresh'], 0, ',', ' ') }}</span>
                            @include('main-projects.partials.trend-badge', ['trend' => $summary['trend_refresh']])
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 d-flex">
                    <div class="info-box mb-0 flex-fill">
                        <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ __('Time in module') }}</span>
                            <span class="info-box-number small">{{ $summary['total_time'] }}</span>
                            @include('main-projects.partials.trend-badge', ['trend' => $summary['trend_seconds']])
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-2 g-md-3 mb-3 cabinet-ms-insights">
                <div class="col-6 col-md-4 col-xl">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-body py-3">
                            <div class="text-secondary small">{{ __('Total events') }}</div>
                            <div class="h5 mb-1">{{ number_format($summary['total_events'], 0, ',', ' ') }}</div>
                            @include('main-projects.partials.trend-badge', ['trend' => $summary['trend_events'], 'inline' => true])
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl">
                    <div class="card shadow-sm h-100 mb-0">
                        <div class="card-body py-3">
                            <div class="text-secondary small">{{ __('Avg per active day') }}</div>
                            <div class="h5 mb-0">{{ number_format($summary['avg_total_per_active_day'], 1, ',', ' ') }}</div>
                            <div class="text-secondary small">{{ __('events') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 col-xl">
                    <div class="card shadow-sm h-100 mb-0 cabinet-ms-prev-card">
                        <div class="card-body py-3 small">
                            <div class="text-secondary mb-1">{{ __('Compared to') }} {{ $summary['prev_from'] }} — {{ $summary['prev_to'] }}</div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge text-bg-light text-body border">⚡ {{ number_format($summary['prev_actions'], 0, ',', ' ') }}</span>
                                <span class="badge text-bg-light text-body border">↻ {{ number_format($summary['prev_refresh'], 0, ',', ' ') }}</span>
                                <span class="badge text-bg-light text-body border"><i class="bi bi-clock me-1"></i>{{ $summary['prev_time'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-2">
                            <h3 class="card-title h6 mb-0">{{ __('Actions vs refreshes') }}</h3>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div class="cabinet-ms-doughnut-wrap">
                                <canvas id="cabinet-ms-doughnut-chart" aria-hidden="true"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-2">
                            <h3 class="card-title h6 mb-0">{{ __('Activity by weekday') }}</h3>
                        </div>
                        <div class="card-body">
                            <div class="cabinet-ms-weekday-wrap">
                                <canvas id="cabinet-ms-weekday-chart" aria-hidden="true"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <h3 class="card-title h6 mb-0">{{ __('Dynamics by day') }}</h3>
                            <div class="btn-group btn-group-sm" role="group" id="cabinet-ms-chart-mode">
                                <input type="radio" class="btn-check" name="ms-chart-mode" id="ms-chart-lines" checked>
                                <label class="btn btn-outline-secondary" for="ms-chart-lines">{{ __('Lines') }}</label>
                                <input type="radio" class="btn-check" name="ms-chart-mode" id="ms-chart-bars">
                                <label class="btn btn-outline-secondary" for="ms-chart-bars">{{ __('Activity bars') }}</label>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3 mb-2 small" id="cabinet-ms-chart-toggles">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="ms-show-actions" checked>
                                    <label class="form-check-label" for="ms-show-actions">{{ __('Actions') }}</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="ms-show-refresh" checked>
                                    <label class="form-check-label" for="ms-show-refresh">{{ __('Refreshes') }}</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="ms-show-time" checked>
                                    <label class="form-check-label" for="ms-show-time">{{ __('Time (min)') }}</label>
                                </div>
                            </div>
                            <div class="cabinet-ms-chart-wrap">
                                <canvas id="cabinet-ms-line-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header py-2">
                            <h3 class="card-title h6 mb-0">{{ __('Top users for period') }}</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0 cabinet-ms-top-users">
                                    <thead class="table-light">
                                    <tr>
                                        <th>{{ __('User') }}</th>
                                        <th class="text-end">{{ __('Actions') }}</th>
                                        <th class="text-end">{{ __('Ref.') }}</th>
                                        <th class="text-end">{{ __('Total') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($report['top_users'] as $u)
                                        <tr>
                                            <td class="text-break small">
                                                @if(!empty($u['user_id']))
                                                    <a href="{{ route('visit.statistics', $u['user_id']) }}" class="text-decoration-none">
                                                        {{ $u['email'] }}
                                                    </a>
                                                @else
                                                    {{ $u['email'] }}
                                                @endif
                                                @if(!empty($u['name']))
                                                    <div class="text-secondary">{{ $u['name'] }}</div>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ number_format($u['actions'], 0, ',', ' ') }}</td>
                                            <td class="text-end">{{ number_format($u['refresh'], 0, ',', ' ') }}</td>
                                            <td class="text-end text-nowrap">
                                                <span class="badge text-bg-primary">{{ number_format($u['total'], 0, ',', ' ') }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-secondary text-center py-3">—</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h3 class="card-title h6 mb-0">{{ __('Daily breakdown') }}</h3>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input type="search" class="form-control form-control-sm cabinet-ms-user-filter" id="ms-user-filter"
                               placeholder="{{ __('Filter by email') }}" autocomplete="off">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="ms-expand-all">{{ __('Expand all') }}</button>
                            <button type="button" class="btn btn-outline-secondary" id="ms-collapse-all">{{ __('Collapse all') }}</button>
                            @if(!empty($report['peak_day']))
                                <button type="button" class="btn btn-outline-warning" id="ms-jump-peak">
                                    <i class="bi bi-lightning-charge"></i> {{ __('Peak') }}
                                </button>
                            @endif
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ms-hide-empty-days" checked>
                            <label class="form-check-label small" for="ms-hide-empty-days">{{ __('Hide days without activity') }}</label>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="accordion accordion-flush cabinet-ms-daily-accordion" id="ms-daily-accordion">
                        @foreach($report['daily'] as $day)
                            @php
                                $isPeak = !empty($report['peak_day']) && $report['peak_day']['date'] === $day['date'];
                                $accId = 'ms-day-' . $day['date'];
                            @endphp
                            <div class="accordion-item cabinet-ms-day-row {{ $day['is_empty'] ? 'cabinet-ms-day-empty' : '' }} {{ $isPeak ? 'cabinet-ms-day-peak' : '' }}"
                                 id="cabinet-ms-day-{{ $day['date'] }}"
                                 data-empty="{{ $day['is_empty'] ? '1' : '0' }}"
                                 data-date="{{ $day['date'] }}">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 {{ $day['is_empty'] ? 'cabinet-ms-day-muted' : '' }}"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $accId }}"
                                            @if($day['users_count'] === 0) disabled @endif>
                                        <span class="cabinet-ms-day-label">
                                            <span class="text-secondary me-2">{{ $day['weekday'] }}</span>
                                            <strong>{{ $day['date_label'] }}</strong>
                                            @if($isPeak)
                                                <span class="badge text-bg-warning ms-2">{{ __('Peak') }}</span>
                                            @endif
                                        </span>
                                        <span class="cabinet-ms-day-metrics ms-auto me-2">
                                            <span class="badge text-bg-light text-body border me-1">{{ $day['time'] }}</span>
                                            <span class="badge text-bg-light text-body border me-1">↻ {{ $day['refreshPageCounter'] }}</span>
                                            <span class="badge text-bg-light text-body border me-1">⚡ {{ $day['actionsCounter'] }}</span>
                                            <span class="badge text-bg-secondary">{{ $day['users_count'] }} {{ __('users') }}</span>
                                        </span>
                                    </button>
                                </h2>
                                <div id="{{ $accId }}" class="accordion-collapse collapse">
                                    <div class="accordion-body p-0">
                                        <table class="table table-sm mb-0 cabinet-ms-users-table">
                                            <thead class="table-light">
                                            <tr>
                                                <th>{{ __('Email') }}</th>
                                                <th class="text-end">{{ __('Time') }}</th>
                                                <th class="text-end">{{ __('Refreshes') }}</th>
                                                <th class="text-end">{{ __('Actions') }}</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($day['users'] as $u)
                                                <tr class="cabinet-ms-user-row" data-email="{{ strtolower($u['email']) }}">
                                                    <td class="text-break">
                                                        @if(!empty($u['user_id']))
                                                            <a href="{{ route('visit.statistics', $u['user_id']) }}">{{ $u['email'] }}</a>
                                                        @else
                                                            {{ $u['email'] }}
                                                        @endif
                                                    </td>
                                                    <td class="text-end font-monospace small">{{ $u['time'] }}</td>
                                                    <td class="text-end">{{ $u['refreshPageCounter'] }}</td>
                                                    <td class="text-end">{{ $u['actionsCounter'] }}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if(is_array($buttonColumns) && count($buttonColumns) > 0)
            @include('main-projects.partials.button-clicks', ['id' => $project->id, 'columns' => $buttonColumns])
        @endif
    </div>
@endsection

@section('js')
    @if(is_array($buttonColumns) && count($buttonColumns) > 0)
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
    @endif
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    <script>
        (function () {
            var chartPayload = @json($chart);
            var distribution = @json($report['distribution'] ?? ['actions' => 0, 'refresh' => 0]);
            var weekday = @json($report['weekday'] ?? ['labels' => [], 'values' => []]);
            var peakDate = @json(!empty($report['peak_day']) ? $report['peak_day']['date'] : null);
            var msChart = null;

            function buildDatasets(mode) {
                if (mode === 'bars') {
                    return [{
                        label: @json(__('Total activity')),
                        data: chartPayload.total,
                        backgroundColor: 'rgba(13, 110, 253, 0.55)',
                        borderColor: '#0d6efd',
                        borderWidth: 1,
                    }];
                }

                var sets = [];
                if (document.getElementById('ms-show-actions').checked) {
                    sets.push({
                        label: @json(__('Actions')),
                        data: chartPayload.actions,
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y',
                    });
                }
                if (document.getElementById('ms-show-refresh').checked) {
                    sets.push({
                        label: @json(__('Refreshes')),
                        data: chartPayload.refresh,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y',
                    });
                }
                if (document.getElementById('ms-show-time').checked) {
                    sets.push({
                        label: @json(__('Time (minutes)')),
                        data: chartPayload.minutes,
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253, 126, 20, 0.1)',
                        tension: 0.3,
                        yAxisID: 'y1',
                    });
                }
                return sets;
            }

            function renderChart() {
                var canvas = document.getElementById('cabinet-ms-line-chart');
                if (!canvas || typeof Chart === 'undefined' || !chartPayload.labels.length) {
                    return;
                }

                var barMode = document.getElementById('ms-chart-bars').checked;
                var toggles = document.getElementById('cabinet-ms-chart-toggles');
                if (toggles) {
                    toggles.style.display = barMode ? 'none' : 'flex';
                }

                if (msChart) {
                    msChart.destroy();
                }

                msChart = new Chart(canvas.getContext('2d'), {
                    type: barMode ? 'bar' : 'line',
                    data: {
                        labels: chartPayload.labels,
                        datasets: buildDatasets(barMode ? 'bars' : 'lines'),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {mode: 'index', intersect: false},
                        plugins: {
                            legend: {position: 'bottom'},
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var v = ctx.parsed.y;
                                        if (ctx.dataset.yAxisID === 'y1' || ctx.dataset.label === @json(__('Time (minutes)'))) {
                                            return ctx.dataset.label + ': ' + v + ' ' + @json(__('min'));
                                        }
                                        return ctx.dataset.label + ': ' + v;
                                    },
                                },
                            },
                        },
                        scales: barMode ? {
                            x: {grid: {display: false}},
                            y: {beginAtZero: true, ticks: {precision: 0}},
                        } : {
                            x: {grid: {display: false}},
                            y: {beginAtZero: true, position: 'left', ticks: {precision: 0}},
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: {drawOnChartArea: false},
                                ticks: {precision: 0},
                            },
                        },
                    },
                });
            }

            ['ms-show-actions', 'ms-show-refresh', 'ms-show-time', 'ms-chart-lines', 'ms-chart-bars'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', renderChart);
                }
            });

            renderChart();

            var distSum = (distribution.actions || 0) + (distribution.refresh || 0);
            if (distSum > 0 && typeof Chart !== 'undefined') {
                var doughnutEl = document.getElementById('cabinet-ms-doughnut-chart');
                if (doughnutEl) {
                    new Chart(doughnutEl.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: [@json(__('Actions')), @json(__('Refreshes'))],
                            datasets: [{
                                data: [distribution.actions, distribution.refresh],
                                backgroundColor: ['#0d6efd', '#198754'],
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

            var weekdayMax = Math.max.apply(null, (weekday.values || []).concat([0]));
            if (weekdayMax > 0 && typeof Chart !== 'undefined') {
                var weekdayEl = document.getElementById('cabinet-ms-weekday-chart');
                if (weekdayEl) {
                    new Chart(weekdayEl.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: weekday.labels || [],
                            datasets: [{
                                label: @json(__('Total activity')),
                                data: weekday.values || [],
                                backgroundColor: 'rgba(13, 110, 253, 0.65)',
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {legend: {display: false}},
                            scales: {
                                x: {beginAtZero: true, ticks: {precision: 0}},
                                y: {grid: {display: false}},
                            },
                        },
                    });
                }
            }

            var hideEmpty = document.getElementById('ms-hide-empty-days');
            function filterEmptyDays() {
                var hide = hideEmpty && hideEmpty.checked;
                document.querySelectorAll('.cabinet-ms-day-row').forEach(function (row) {
                    var empty = row.getAttribute('data-empty') === '1';
                    row.style.display = hide && empty ? 'none' : '';
                });
            }
            if (hideEmpty) {
                hideEmpty.addEventListener('change', filterEmptyDays);
                filterEmptyDays();
            }

            function setAccordionOpen(open) {
                document.querySelectorAll('#ms-daily-accordion .accordion-collapse').forEach(function (panel) {
                    var btn = panel.closest('.accordion-item')
                        ? panel.closest('.accordion-item').querySelector('.accordion-button')
                        : null;
                    if (!btn || btn.disabled) {
                        return;
                    }
                    if (open) {
                        panel.classList.add('show');
                        btn.classList.remove('collapsed');
                        btn.setAttribute('aria-expanded', 'true');
                    } else {
                        panel.classList.remove('show');
                        btn.classList.add('collapsed');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            var expandAll = document.getElementById('ms-expand-all');
            var collapseAll = document.getElementById('ms-collapse-all');
            if (expandAll) {
                expandAll.addEventListener('click', function () { setAccordionOpen(true); });
            }
            if (collapseAll) {
                collapseAll.addEventListener('click', function () { setAccordionOpen(false); });
            }

            var jumpPeak = document.getElementById('ms-jump-peak');
            if (jumpPeak && peakDate) {
                jumpPeak.addEventListener('click', function () {
                    var row = document.getElementById('cabinet-ms-day-' + peakDate);
                    if (!row) {
                        return;
                    }
                    row.style.display = '';
                    var panel = row.querySelector('.accordion-collapse');
                    var btn = row.querySelector('.accordion-button');
                    if (panel && btn && !btn.disabled) {
                        panel.classList.add('show');
                        btn.classList.remove('collapsed');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                    row.scrollIntoView({behavior: 'smooth', block: 'center'});
                });
            }

            var userFilter = document.getElementById('ms-user-filter');
            if (userFilter) {
                userFilter.addEventListener('input', function () {
                    var q = userFilter.value.trim().toLowerCase();
                    document.querySelectorAll('.cabinet-ms-user-row').forEach(function (tr) {
                        var email = tr.getAttribute('data-email') || '';
                        tr.style.display = !q || email.indexOf(q) !== -1 ? '' : 'none';
                    });
                    if (q) {
                        document.querySelectorAll('.cabinet-ms-day-row').forEach(function (dayRow) {
                            if (dayRow.getAttribute('data-empty') === '1') {
                                return;
                            }
                            var visible = dayRow.querySelectorAll('.cabinet-ms-user-row').length > 0
                                && Array.prototype.some.call(
                                    dayRow.querySelectorAll('.cabinet-ms-user-row'),
                                    function (tr) { return tr.style.display !== 'none'; }
                                );
                            dayRow.style.display = visible ? '' : 'none';
                        });
                    } else {
                        filterEmptyDays();
                    }
                });
            }
        })();
    </script>
@endsection
