@php
    $staleIdPrefix = $staleIdPrefix ?? 'cabinet-mon-admin-stale';
    $staleExpanded = !empty($staleExpanded);
    $staleShowLogic = !empty($staleShowLogic);
    $staleTitle = $staleTitle ?? __('Monitoring admin stale schedules title');
    $staleFilterOnUsersPage = !empty($staleFilterOnUsersPage);
@endphp
{{-- Зависшие расписания: MonitoringStaleScheduleReport. Подключается с /users и /monitoring/admin. --}}
<div class="card shadow-sm mb-3 cabinet-stale-schedules" id="{{ $staleIdPrefix }}-panel">
    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <button class="btn btn-link text-decoration-none p-0 fw-semibold text-body d-flex align-items-center gap-2"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $staleIdPrefix }}-collapse"
                aria-expanded="{{ $staleExpanded ? 'true' : 'false' }}"
                aria-controls="{{ $staleIdPrefix }}-collapse">
            <i class="bi bi-clock-history text-warning" aria-hidden="true"></i>
            <span>{{ $staleTitle }}</span>
            <span class="badge text-bg-warning">{{ number_format($staleMonitoring['projects'] ?? 0, 0, ',', ' ') }}</span>
        </button>
        <div class="d-flex flex-wrap align-items-center gap-2 small text-secondary">
            <span>{{ __('Monitoring admin stale schedules hint', ['days' => $staleMonitoring['inactive_days'] ?? 90]) }}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="{{ $staleIdPrefix }}-reload" aria-label="{{ __('Refresh') }}">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>
    <div id="{{ $staleIdPrefix }}-collapse" class="collapse{{ $staleExpanded ? ' show' : '' }}">
        @if($staleShowLogic)
            <div class="card-body border-bottom py-3 small text-secondary">
                <p class="mb-2">{{ __('Monitoring admin stale schedules logic lead') }}</p>
                <ul class="mb-0 ps-3">
                    <li>{{ __('Monitoring admin stale schedules logic item 1') }}</li>
                    <li>{{ __('Monitoring admin stale schedules logic item 2') }}</li>
                    <li>{{ __('Monitoring admin stale schedules logic item 3') }}</li>
                    <li>{{ __('Monitoring admin stale schedules logic item 4') }}</li>
                </ul>
            </div>
        @endif
        <div class="card-body border-bottom py-2">
            <div class="row g-2 text-center {{ $staleIdPrefix }}-kpi">
                <div class="col-6 col-md-3">
                    <div class="small text-secondary">{{ __('Users stale monitoring projects') }}</div>
                    <div class="fw-semibold" id="{{ $staleIdPrefix }}-kpi-projects">{{ number_format($staleMonitoring['projects'] ?? 0, 0, ',', ' ') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-secondary">{{ __('Users stale monitoring users') }}</div>
                    <div class="fw-semibold" id="{{ $staleIdPrefix }}-kpi-users">{{ number_format($staleMonitoring['users'] ?? 0, 0, ',', ' ') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-secondary">{{ __('Users stale monitoring regions') }}</div>
                    <div class="fw-semibold" id="{{ $staleIdPrefix }}-kpi-regions">{{ number_format($staleMonitoring['auto_regions'] ?? 0, 0, ',', ' ') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="small text-secondary">{{ __('Words') }}</div>
                    <div class="fw-semibold" id="{{ $staleIdPrefix }}-kpi-keywords">{{ number_format($staleMonitoring['keywords'] ?? 0, 0, ',', ' ') }}</div>
                </div>
            </div>
        </div>
        <div class="card-body border-bottom py-2">
            <div class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label small mb-1" for="{{ $staleIdPrefix }}-days">{{ __('Users stale monitoring inactive days') }}</label>
                    <input type="number" class="form-control form-control-sm" id="{{ $staleIdPrefix }}-days"
                           value="{{ $staleMonitoring['inactive_days'] ?? 90 }}" min="1" max="730" style="width:6rem">
                </div>
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="{{ $staleIdPrefix }}-free-only">
                    <label class="form-check-label small" for="{{ $staleIdPrefix }}-free-only">{{ __('Users stale monitoring free only') }}</label>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="{{ $staleIdPrefix }}-apply">{{ __('Apply filters') }}</button>
                @if($staleFilterOnUsersPage)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="{{ $staleIdPrefix }}-filter-users">
                        {{ __('Users stale monitoring filter users') }}
                    </button>
                @else
                    <a href="{{ route('users.index') }}?filter_stale_monitoring=1#{{ $staleIdPrefix }}-panel" class="btn btn-sm btn-outline-primary">
                        {{ __('Monitoring admin stale schedules filter users') }}
                    </a>
                @endif
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 w-100" id="{{ $staleIdPrefix }}-table">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('Domain') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Was online') }}</th>
                        <th>{{ __('Tariff') }}</th>
                        <th class="text-end">{{ __('Words') }}</th>
                        <th>{{ __('Users stale monitoring schedule') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
