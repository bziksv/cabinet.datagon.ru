@extends('layouts.app')

@section('title', __('General statistics users'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
    <link rel="stylesheet" href="{{ asset('css/cabinet-visits-statistics.css') }}">
@endsection

@section('content')
    @php
        $summary = $report['summary'];
        $rows = $report['rows'];
        $pageUrl = route('users.statistics');
    @endphp

    <div class="cabinet-visits-stats-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2">
                    <i class="bi bi-graph-up-arrow me-2 text-primary" aria-hidden="true"></i>{{ __('General statistics users') }}
                </h2>
                <p class="text-secondary small mb-0">
                    {{ __('Aggregated visits across all modules for users with tracking enabled (statistic=1). Sorted by time in modules.') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-people me-1"></i>{{ __('Users') }}
                </a>
                <a href="{{ route('statistics.modules') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-grid me-1"></i>{{ __('General statistics modules') }}
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="get" action="{{ $pageUrl }}" class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="vs-from">{{ __('Period from') }}</label>
                        <input type="date" class="form-control form-control-sm" id="vs-from" name="from" value="{{ $dateFrom }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1" for="vs-to">{{ __('Period to') }}</label>
                        <input type="date" class="form-control form-control-sm" id="vs-to" name="to" value="{{ $dateTo }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1" for="vs-limit">{{ __('Top users') }}</label>
                        <select class="form-select form-select-sm" id="vs-limit" name="limit">
                            @foreach([100, 250, 500, 1000, 2000] as $opt)
                                <option value="{{ $opt }}" {{ (int) $limit === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check-lg me-1"></i>{{ __('Apply') }}
                        </button>
                        @foreach([7 => __('7 days'), 30 => __('30 days'), 60 => __('60 days')] as $days => $label)
                            <a href="{{ $pageUrl }}?from={{ now()->subDays($days - 1)->format('Y-m-d') }}&to={{ now()->format('Y-m-d') }}&limit={{ $limit }}"
                               class="btn btn-sm btn-outline-secondary {{ $dateFrom === now()->subDays($days - 1)->format('Y-m-d') && $dateTo === now()->format('Y-m-d') ? 'active' : '' }}">{{ $label }}</a>
                        @endforeach
                        <a href="{{ $pageUrl }}" class="btn btn-sm btn-link">{{ __('All time') }}</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-md-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-people"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('In table') }}</span>
                        <span class="info-box-number">{{ number_format($summary['users_shown'], 0, ',', ' ') }}</span>
                        <span class="info-box-text small">{{ __('of') }} {{ number_format($summary['limit'], 0, ',', ' ') }} {{ __('max') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-hand-index"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Actions') }}</span>
                        <span class="info-box-number">{{ number_format($summary['total_actions'], 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-arrow-repeat"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Page refreshes') }}</span>
                        <span class="info-box-number">{{ number_format($summary['total_refresh'], 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Time in modules') }}</span>
                        <span class="info-box-number small font-monospace">{{ $summary['total_time'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if(empty($rows))
            <div class="alert alert-light border text-center py-5">
                <i class="bi bi-inbox text-secondary display-6 d-block mb-2"></i>
                <p class="mb-0">{{ __('No visit data for the selected period.') }}</p>
            </div>
        @else
            <div class="card shadow-sm">
                <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h3 class="card-title h6 mb-0">{{ __('User ranking') }}</h3>
                    <span class="text-secondary small">
                        {{ $report['has_period'] ? __('Filtered period') : __('For all time') }}
                        · {{ number_format($summary['total_events'], 0, ',', ' ') }} {{ __('events') }}
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="cabinet-visits-stats-table" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('User') }}</th>
                                <th>{{ __('Roles') }}</th>
                                <th class="text-end">{{ __('Refreshes') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                                <th class="text-end">{{ __('Time in module') }}</th>
                                <th>{{ __('utm metrics') }}</th>
                                <th class="text-end">{{ __('Details') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    <td class="text-nowrap" data-order="{{ $row['user_id'] }}">{{ $row['user_id'] }}</td>
                                    <td>
                                        <a href="{{ route('users.index') }}?filter_q={{ urlencode($row['email']) }}" class="text-break fw-semibold text-decoration-none">
                                            {{ $row['email'] }}
                                        </a>
                                        @if($row['name'])
                                            <div class="small text-secondary">{{ $row['name'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @forelse($row['roles'] as $role)
                                            <span class="badge text-bg-secondary me-1 mb-1">{{ $role }}</span>
                                        @empty
                                            <span class="text-secondary">—</span>
                                        @endforelse
                                    </td>
                                    <td class="text-end" data-order="{{ $row['refresh'] }}">{{ number_format($row['refresh'], 0, ',', ' ') }}</td>
                                    <td class="text-end" data-order="{{ $row['actions'] }}">{{ number_format($row['actions'], 0, ',', ' ') }}</td>
                                    <td class="text-end font-monospace text-nowrap" data-order="{{ $row['seconds'] }}">{{ $row['time'] }}</td>
                                    <td data-order="{{ $row['utm_source'] ?? '' }}">
                                        @if($row['utm_source'])
                                            <span class="badge text-bg-light text-body border">utm_source: {{ $row['utm_source'] }}</span>
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('visit.statistics', $row['user_id']) }}"
                                           class="btn btn-sm btn-outline-info"
                                           title="{{ __('User statistic') }}">
                                            <i class="bi bi-pie-chart"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
    <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.min.js') }}"></script>
    <script>
        $(function () {
            var tableEl = $('#cabinet-visits-stats-table');
            if (!tableEl.length) {
                return;
            }

            tableEl.DataTable({
                order: [[5, 'desc']],
                pageLength: 50,
                lengthMenu: [25, 50, 100, 250],
                dom: '<"row align-items-center g-2 px-2 pt-2"<"col-sm-auto"l><"col-sm-auto"B>>rt<"row px-2 pb-2"<"col-sm-auto"i><"col-sm"p>>',
                buttons: ['copy', 'csv', 'excel'],
                language: {
                    lengthMenu: '_MENU_',
                    search: '',
                    searchPlaceholder: @json(__('Search')),
                    paginate: {previous: '‹', next: '›'},
                    emptyTable: @json(__('No data available in table')),
                },
                columnDefs: [
                    {orderable: false, targets: [7]},
                ],
            });
        });
    </script>
@endsection
