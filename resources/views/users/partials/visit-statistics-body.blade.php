@php
    $summary = $report['summary'];
    $modules = $report['modules'];
    $chart = $report['chart'];
    $doughnut = $report['doughnut'];
@endphp

@if(!$report['has_data'])
    <div class="alert alert-light border text-center py-4 cabinet-uvs-empty">
        <p class="mb-0">{{ __('No visit data for the selected period.') }}</p>
    </div>
@else
    <div class="row g-2 g-md-3 mb-3 cabinet-uvs-kpi">
        <div class="col-6 col-md-3 d-flex">
            <div class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-grid"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Modules') }}</span>
                    <span class="info-box-number">{{ $summary['modules_count'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 d-flex">
            <div class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-hand-index"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Actions') }}</span>
                    <span class="info-box-number">{{ number_format($summary['actions'], 0, ',', ' ') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 d-flex">
            <div class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-arrow-repeat"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Page refreshes') }}</span>
                    <span class="info-box-number">{{ number_format($summary['refresh'], 0, ',', ' ') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 d-flex">
            <div class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-clock"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Time in modules') }}</span>
                    <span class="info-box-number small font-monospace">{{ $summary['time'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                    <h3 class="card-title h6 mb-0">{{ __('Dynamics by day') }}</h3>
                </div>
                <div class="card-body">
                    <div class="cabinet-uvs-chart-wrap">
                        <canvas id="cabinet-uvs-line-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                    <h3 class="card-title h6 mb-0">{{ __('By module') }}</h3>
                </div>
                <div class="card-body">
                    <div class="cabinet-uvs-doughnut-wrap">
                        <canvas id="cabinet-uvs-doughnut-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2">
            <h3 class="card-title h6 mb-0">{{ __('Module list') }}</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="cabinet-uvs-modules-table" class="table table-sm table-striped table-hover mb-0 w-100">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('Module') }}</th>
                        <th class="text-end">{{ __('Time') }}</th>
                        <th class="text-end">{{ __('Refreshes') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                        <th class="text-end">{{ __('Total') }}</th>
                        <th>{{ __('Last visit') }}</th>
                        <th class="text-end"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($modules as $m)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="cabinet-uvs-module-icon" style="background:{{ $m['color'] }};">
                                        <i class="bi bi-box"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ $m['link'] }}" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">{{ $m['title'] }}</a>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end font-monospace text-nowrap" data-order="{{ $m['seconds'] }}">{{ $m['time'] }}</td>
                            <td class="text-end" data-order="{{ $m['refresh'] }}">{{ number_format($m['refresh'], 0, ',', ' ') }}</td>
                            <td class="text-end" data-order="{{ $m['actions'] }}">{{ number_format($m['actions'], 0, ',', ' ') }}</td>
                            <td class="text-end" data-order="{{ $m['total'] }}">
                                <span class="badge text-bg-primary">{{ number_format($m['total'], 0, ',', ' ') }}</span>
                            </td>
                            <td class="text-nowrap">{{ $m['last_visit'] ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                @if($m['stats_url'])
                                    <a href="{{ $m['stats_url'] }}" class="btn btn-sm btn-outline-info" title="{{ __('Module statistics') }}">
                                        <i class="bi bi-bar-chart"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script type="application/json" id="cabinet-uvs-chart-data">@json($chart)</script>
    <script type="application/json" id="cabinet-uvs-doughnut-data">@json($doughnut)</script>
@endif
