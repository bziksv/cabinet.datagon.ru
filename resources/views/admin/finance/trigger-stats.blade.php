@extends('layouts.app')

@section('title', __('Trigger stats page title', ['name' => $campaign->name]))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-finance-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-finance-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-finance-admin-page cabinet-finance-trigger-stats-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
            <div>
                <a href="{{ route('admin.finance.index', ['tab' => 'trigger', 'campaign' => $campaign->id]) }}"
                   class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('Trigger stats back') }}
                </a>
                <h2 class="h4 mb-1">
                    <i class="bi bi-envelope-check text-info me-1"></i>
                    {{ __('Trigger stats page title', ['name' => $campaign->name]) }}
                </h2>
                <p class="text-secondary small mb-0">{{ __('Trigger stats page lead') }}</p>
            </div>
        </div>

        <div class="row g-3 mb-4 cabinet-finance-trigger-stats-kpis">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="cabinet-finance-trigger-stat">
                    <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat sent') }}</span>
                    <strong class="cabinet-finance-trigger-stat__value">{{ number_format($stats['sent'], 0, '.', ' ') }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="cabinet-finance-trigger-stat">
                    <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat opened') }}</span>
                    <strong class="cabinet-finance-trigger-stat__value text-primary">{{ number_format($stats['opened'], 0, '.', ' ') }}</strong>
                    <span class="cabinet-finance-trigger-stat__meta">{{ $stats['open_rate'] }}%</span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="cabinet-finance-trigger-stat">
                    <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat redeemed') }}</span>
                    <strong class="cabinet-finance-trigger-stat__value text-success">{{ number_format($stats['redeemed'], 0, '.', ' ') }}</strong>
                    <span class="cabinet-finance-trigger-stat__meta">{{ $stats['conversion'] }}%</span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="cabinet-finance-trigger-stat">
                    <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat pending') }}</span>
                    <strong class="cabinet-finance-trigger-stat__value">{{ number_format($stats['pending'], 0, '.', ' ') }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="cabinet-finance-trigger-stat">
                    <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat failed') }}</span>
                    <strong class="cabinet-finance-trigger-stat__value text-danger">{{ number_format($stats['failed'], 0, '.', ' ') }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="cabinet-finance-trigger-stat">
                    <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat audience') }}</span>
                    <strong class="cabinet-finance-trigger-stat__value">{{ number_format($stats['audience'], 0, '.', ' ') }}</strong>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">{{ __('Trigger stats funnel title') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="cabinet-finance-trigger-stats-chart-wrap">
                            <canvas id="trigger-stats-funnel-chart" aria-label="{{ __('Trigger stats funnel title') }}"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">{{ __('Trigger stats timeline title') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="cabinet-finance-trigger-stats-chart-wrap cabinet-finance-trigger-stats-chart-wrap--timeline">
                            <canvas id="trigger-stats-timeline-chart" aria-label="{{ __('Trigger stats timeline title') }}"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h3 class="card-title h6 mb-0">
                    <i class="bi bi-table me-1"></i>{{ __('Trigger dispatches title') }}
                </h3>
                <form method="get" action="{{ route('admin.finance.trigger.stats', $campaign) }}" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label visually-hidden" for="trigger-stats-filter">{{ __('Status') }}</label>
                        <select name="filter" id="trigger-stats-filter" class="form-select form-select-sm">
                            @foreach($filterOptions as $value => $label)
                                <option value="{{ $value }}" {{ $filter === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Apply') }}</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3">{{ __('User') }}</th>
                            <th scope="col">{{ __('Promo code field code') }}</th>
                            <th scope="col">{{ __('Sum') }}</th>
                            <th scope="col">{{ __('Trigger dispatch sent') }}</th>
                            <th scope="col">{{ __('Trigger dispatch opened') }}</th>
                            <th scope="col">{{ __('Trigger dispatch redeemed') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end pe-3">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($dispatches as $dispatch)
                            @php
                                $dispatchUser = $dispatch->user;
                                $dispatchName = $dispatchUser
                                    ? trim(($dispatchUser->name ?? '') . ' ' . ($dispatchUser->last_name ?? ''))
                                    : '';
                                if ($dispatchName === '' && $dispatchUser) {
                                    $dispatchName = $dispatchUser->email;
                                }
                                $visitFrom = $dispatch->sent_at ? $dispatch->sent_at->format('Y-m-d') : null;
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    @if($dispatchUser)
                                        <a href="{{ route('users.edit', $dispatchUser->id) }}" class="fw-semibold">{{ $dispatchName }}</a>
                                        <span class="small text-secondary d-block">#{{ $dispatchUser->id }} · {{ $dispatchUser->email }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><code>{{ optional($dispatch->promoCode)->code }}</code></td>
                                <td>
                                    @if($dispatch->promoCode)
                                        +{{ number_format((int) $dispatch->promoCode->bonus_value, 0, '.', ' ') }} ₽
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-nowrap small">
                                    {{ $dispatch->sent_at ? $dispatch->sent_at->format('d.m.Y H:i') : '—' }}
                                </td>
                                <td class="text-nowrap small">
                                    @if($dispatch->isOpened())
                                        <span class="text-primary">
                                            <i class="bi bi-envelope-open me-1"></i>{{ $dispatch->opened_at->format('d.m.Y H:i') }}
                                        </span>
                                        @if($dispatch->open_count > 1)
                                            <span class="badge text-bg-light text-secondary border ms-1">×{{ $dispatch->open_count }}</span>
                                        @endif
                                    @else
                                        <span class="text-secondary">{{ __('Trigger dispatch not opened') }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap small text-secondary">
                                    {{ $dispatch->redeemed_at ? $dispatch->redeemed_at->format('d.m.Y H:i') : '—' }}
                                </td>
                                <td>
                                    @if($dispatch->isRedeemed())
                                        <span class="badge text-bg-success">{{ __('Trigger dispatch status redeemed') }}</span>
                                    @elseif($dispatch->status === 'sent')
                                        <span class="badge text-bg-primary">{{ $dispatch->isOpened() ? __('Trigger dispatch status opened') : __('Trigger dispatch status sent') }}</span>
                                    @elseif($dispatch->status === 'failed')
                                        <span class="badge text-bg-danger">{{ __('Trigger dispatch status failed') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Trigger dispatch status pending') }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($dispatchUser)
                                        <a href="{{ $visitFrom ? route('visit.statistics', $dispatchUser->id) . '?from=' . $visitFrom . '&to=' . now()->format('Y-m-d') : route('visit.statistics', $dispatchUser->id) }}"
                                           class="btn btn-sm btn-outline-info"
                                           title="{{ __('Trigger stats visit link') }}">
                                            <i class="bi bi-pie-chart"></i>
                                        </a>
                                        <a href="{{ route('users.edit', $dispatchUser->id) }}"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="{{ __('User') }}">
                                            <i class="bi bi-person"></i>
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">{{ __('Trigger dispatches empty') }}</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($dispatches->hasPages())
                <div class="card-footer">{{ $dispatches->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            var funnelCanvas = document.getElementById('trigger-stats-funnel-chart');
            if (funnelCanvas) {
                new Chart(funnelCanvas, {
                    type: 'bar',
                    data: {
                        labels: [
                            @json(__('Trigger stat sent')),
                            @json(__('Trigger stat opened')),
                            @json(__('Trigger stat redeemed')),
                        ],
                        datasets: [{
                            data: @json(array_values($chart['funnel'])),
                            backgroundColor: [
                                'rgba(13, 202, 240, 0.75)',
                                'rgba(13, 110, 253, 0.75)',
                                'rgba(25, 135, 84, 0.75)',
                            ],
                            borderRadius: 6,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                            },
                        },
                    },
                });
            }

            var timelineCanvas = document.getElementById('trigger-stats-timeline-chart');
            if (timelineCanvas) {
                new Chart(timelineCanvas, {
                    type: 'line',
                    data: {
                        labels: @json($chart['timeline']['labels']),
                        datasets: [
                            {
                                label: @json(__('Trigger stat sent')),
                                data: @json($chart['timeline']['sent']),
                                borderColor: 'rgb(13, 202, 240)',
                                backgroundColor: 'rgba(13, 202, 240, 0.15)',
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: @json(__('Trigger stat opened')),
                                data: @json($chart['timeline']['opened']),
                                borderColor: 'rgb(13, 110, 253)',
                                backgroundColor: 'rgba(13, 110, 253, 0.12)',
                                tension: 0.3,
                                fill: true,
                            },
                            {
                                label: @json(__('Trigger stat redeemed')),
                                data: @json($chart['timeline']['redeemed']),
                                borderColor: 'rgb(25, 135, 84)',
                                backgroundColor: 'rgba(25, 135, 84, 0.12)',
                                tension: 0.3,
                                fill: true,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top' },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                            },
                        },
                    },
                });
            }
        })();
    </script>
@endsection
