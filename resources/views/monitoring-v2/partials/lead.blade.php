<div class="cabinet-mon-v2-lead px-4 py-3">
    <div class="d-flex flex-wrap align-items-start gap-3">
        <span class="cabinet-mon-v2-lead__icon" aria-hidden="true">
            <i class="bi bi-graph-up-arrow"></i>
        </span>
        <div class="flex-grow-1 min-w-0">
            <p class="mb-1 fw-semibold text-body">{{ __('Monitoring v2 lead title') }}</p>
            <p class="mb-0 small text-secondary">{{ __('Monitoring v2 lead hint') }}</p>
        </div>
        <a href="{{ route('monitoring.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">
            <i class="bi bi-layout-text-sidebar me-1" aria-hidden="true"></i>{{ __('Monitoring v2 classic ui') }}
        </a>
    </div>
</div>
