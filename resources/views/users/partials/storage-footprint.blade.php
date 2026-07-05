<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2 cabinet-users-footprint-bar">
    <div class="small text-secondary">
        {{ __('Users storage footprint hint') }}
        @if(!empty($footprintRefreshedAt))
            <span class="ms-1">· {{ __('Users storage footprint updated') }} {{ \Carbon\Carbon::parse($footprintRefreshedAt)->diffForHumans() }}</span>
        @endif
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinet-users-footprint-refresh-all">
        <i class="bi bi-database me-1"></i>{{ __('Users storage footprint refresh all') }}
    </button>
</div>
<div class="card shadow-sm mb-3 d-none cabinet-users-footprint-progress" id="cabinet-users-footprint-progress" aria-live="polite">
    <div class="card-body py-2 px-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div class="d-flex align-items-center gap-2 small fw-semibold">
                <span class="spinner-border spinner-border-sm text-primary" id="cabinet-users-footprint-progress-spinner" role="status" aria-hidden="true"></span>
                <span id="cabinet-users-footprint-progress-title">{{ __('Users storage footprint progress title') }}</span>
            </div>
            <span class="badge text-bg-primary" id="cabinet-users-footprint-progress-percent">0%</span>
        </div>
        <div class="progress mb-2" style="height: 0.65rem;">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 id="cabinet-users-footprint-progress-bar"
                 role="progressbar"
                 style="width: 0%"
                 aria-valuenow="0"
                 aria-valuemin="0"
                 aria-valuemax="100"></div>
        </div>
        <div class="small text-secondary" id="cabinet-users-footprint-progress-status"></div>
    </div>
</div>
