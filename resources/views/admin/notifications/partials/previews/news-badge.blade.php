<div class="cabinet-notify-preview-modal text-center py-4">
    <p class="text-secondary small mb-3">{{ __('Users notify event news example') }}</p>
    <div class="d-inline-flex align-items-center gap-2 px-3 py-2 border rounded bg-body">
        <i class="bi bi-newspaper fs-4 text-primary" aria-hidden="true"></i>
        <span class="fw-medium">{{ __('Users notify module news') }}</span>
        <span class="badge rounded-pill text-bg-danger">3</span>
    </div>
    <p class="small text-secondary mt-3 mb-2">{{ __('Users notify preview in app only') }}</p>
    @if(Route::has('news'))
        <a href="{{ route('news') }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Users notify open news page') }}
        </a>
    @endif
</div>
