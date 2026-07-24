@php
    /** @var \App\Support\SiteMonitoringListSummary $summary */
@endphp
<p class="text-secondary cabinet-sm-kpi-hint mb-3 mb-md-4">
    {{ __('Site monitoring list kpi hint') }}
</p>

<div class="row g-3 mb-4 cabinet-sm-kpi cabinet-module-kpi" aria-live="polite" data-sm-list-kpi>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-secondary shadow-sm">
                <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi total') }}</span>
                <span class="info-box-number" data-sm-kpi="total">{{ $summary->total }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi available') }}</span>
                <span class="info-box-number @if($summary->available > 0) text-success @endif" data-sm-kpi="available">{{ $summary->available }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-danger shadow-sm">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi with issues') }}</span>
                <span class="info-box-number @if($summary->withIssues > 0) text-danger @endif" data-sm-kpi="issues">{{ $summary->withIssues }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-primary shadow-sm">
                <i class="bi bi-speedometer2" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi avg uptime') }}</span>
                <span class="info-box-number" data-sm-kpi="uptime">{{ $summary->formatAvgUptime() }}</span>
            </div>
        </div>
    </div>
</div>

<p class="small text-secondary mb-3 cabinet-sm-kpi-awaiting @if($summary->awaitingCheck <= 0) d-none @endif"
   data-sm-kpi-awaiting
   data-template="{{ __('Site monitoring kpi awaiting check', ['count' => ':count']) }}">
    <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
    <span data-sm-kpi-awaiting-text>{{ __('Site monitoring kpi awaiting check', ['count' => $summary->awaitingCheck]) }}</span>
</p>
