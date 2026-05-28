<div class="alert alert-danger d-none mb-0" id="cabinet-mon-v2-load-error" role="alert"></div>

<div class="cabinet-mon-v2-progress d-none" id="cabinet-mon-v2-progress" aria-hidden="true">
    <div class="progress" style="height: 4px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
    </div>
    <p class="small text-secondary mb-0 mt-1" id="cabinet-mon-v2-progress-label"></p>
</div>

@if($count < 1)
    <div class="cabinet-mon-v2-empty">
        <i class="bi bi-folder2-open display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
        <p class="fw-semibold mb-1">{{ __('Monitoring v2 empty title') }}</p>
        <p class="text-secondary small mb-3">{{ __('Monitoring v2 empty hint') }}</p>
        <a href="{{ route('monitoring.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Create project') }}
        </a>
    </div>
@else
    <div class="cabinet-mon-v2-skeleton" id="cabinet-mon-v2-skeleton" aria-hidden="false">
        @for($i = 0; $i < min(3, $count); $i++)
            <div class="cabinet-mon-v2-card cabinet-mon-v2-card--skeleton" aria-hidden="true"></div>
        @endfor
    </div>

    <div class="cabinet-mon-v2-grid d-none" id="cabinet-mon-v2-grid"></div>

    <div class="cabinet-mon-v2-table-wrap d-none" id="cabinet-mon-v2-table-wrap">
        <div class="card table-card border-0 shadow-sm">
            <table class="table table-hover projects w-100 mb-0" id="cabinet-mon-v2-projects"></table>
        </div>
    </div>

    <p class="text-secondary small text-center d-none mb-0" id="cabinet-mon-v2-no-results">
        {{ __('Monitoring v2 no search results') }}
    </p>
@endif
