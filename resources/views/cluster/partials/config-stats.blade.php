<div class="info-box shadow-sm mb-3">
    <span class="info-box-icon text-bg-primary">
        <i class="fas fa-chart-bar" aria-hidden="true"></i>
    </span>
    <div class="info-box-content">
        <span class="info-box-text">{{ __('General statistics of the module') }}</span>
        <span class="info-box-number">{{ number_format($counter ?? 0, 0, ',', ' ') }}</span>
        <span class="info-box-text">{{ __('Scans in the current month') }}</span>
    </div>
</div>

<div class="card shadow-sm cabinet-cluster-users-stats-card">
    <div class="card-header">
        <h3 class="card-title">{{ __('Unique users') }}</h3>
    </div>
    <ul class="list-group list-group-flush">
        @foreach([30, 60, 90] as $days)
            <li class="list-group-item d-flex align-items-center justify-content-between gap-3">
                <span class="text-secondary">{{ $days }} {{ __('days') }}</span>
                <strong class="cabinet-cluster-users-stat-value">{{ number_format($uniqueUsers[$days] ?? 0, 0, ',', ' ') }}</strong>
            </li>
        @endforeach
    </ul>
    <div class="card-footer text-secondary small py-2">
        По сохранённым проектам кластеризации (уникальные user_id за период).
    </div>
</div>
