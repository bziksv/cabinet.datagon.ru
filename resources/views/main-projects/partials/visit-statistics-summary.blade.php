@php
    $formatVisitSeconds = static function (int $seconds) {
        if ($seconds <= 0) {
            return '00:00:00';
        }

        return \Carbon\Carbon::now()->addSeconds($seconds)->diff(\Carbon\Carbon::now())->format('%H:%I:%S');
    };
@endphp

<div class="card shadow-sm cabinet-mp-visit-stats" id="cabinet-mp-visit-stats">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h3 class="card-title h6 mb-1">
                <i class="bi bi-bar-chart-line me-1 text-info"></i>{{ __('Visit statistics summary') }}
            </h3>
            <p class="text-secondary small mb-0">{{ __('Aggregated data for all modules. Detailed charts open in separate pages.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('statistics.modules') }}" class="btn btn-sm btn-outline-info">
                <i class="bi bi-grid me-1"></i>{{ __('General statistics modules') }}
            </a>
            @if(!empty($showUserStatistics))
                <a href="{{ route('users.statistics') }}" class="btn btn-sm btn-outline-info">
                    <i class="bi bi-graph-up me-1"></i>{{ __('General statistics users') }}
                </a>
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 cabinet-mp-visit-stats-table">
                <thead class="table-light">
                <tr>
                    <th scope="col">{{ __('Module') }}</th>
                    <th scope="col" class="text-center">{{ __('Tracking') }}</th>
                    <th scope="col" class="text-end">{{ __('Actions') }}</th>
                    <th scope="col" class="text-end">{{ __('Page refreshes') }}</th>
                    <th scope="col" class="text-end">{{ __('Time in module') }}</th>
                    <th scope="col" class="text-end">{{ __('Details') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($moduleStats as $row)
                    @php
                        $color = $row['color'] && preg_match('/^#[0-9A-Fa-f]{6}$/', $row['color'])
                            ? $row['color'] : '#0d6efd';
                        $stat = $row['statistics'];
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="cabinet-mp-icon-preview cabinet-mp-icon-preview--sm"
                                      style="background: {{ $color }};"
                                      aria-hidden="true">{!! $row['icon'] ?? '' !!}</span>
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ __($row['title']) }}</div>
                                    <a href="{{ $row['link'] }}" class="small text-secondary" target="_blank" rel="noopener">{{ $row['link'] }}</a>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            @if($row['tracking'])
                                <span class="badge text-bg-info">{{ __('On') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Off') }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">{{ number_format($stat['actions_counter'], 0, ',', ' ') }}</td>
                        <td class="text-end text-nowrap">{{ number_format($stat['refresh_page_counter'], 0, ',', ' ') }}</td>
                        <td class="text-end text-nowrap font-monospace small">{{ $formatVisitSeconds($stat['seconds']) }}</td>
                        <td class="text-end text-nowrap">
                            @if($row['tracking'])
                                <a href="{{ route('main-projects.statistics', $row['id']) }}"
                                   class="btn btn-sm btn-outline-info"
                                   title="{{ __('Statistics') }}">
                                    <i class="bi bi-bar-chart"></i>
                                </a>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="2">{{ __('Total for all modules') }}</td>
                    <td class="text-end text-nowrap">{{ number_format($visitTotals['actions'], 0, ',', ' ') }}</td>
                    <td class="text-end text-nowrap">{{ number_format($visitTotals['refresh'], 0, ',', ' ') }}</td>
                    <td class="text-end text-nowrap font-monospace small">{{ $formatVisitSeconds($visitTotals['seconds']) }}</td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
