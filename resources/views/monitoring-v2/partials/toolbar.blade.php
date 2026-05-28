<div class="cabinet-mon-v2-toolbar card shadow-sm border-0">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-lg-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="bi bi-search text-secondary" aria-hidden="true"></i>
                    </span>
                    <input type="search"
                           id="cabinet-mon-v2-search"
                           class="form-control border-start-0"
                           placeholder="{{ __('Monitoring v2 search placeholder') }}"
                           autocomplete="off"
                           @if($count < 1) disabled @endif>
                </div>
            </div>
            <div class="col-lg-3">
                <select id="cabinet-mon-v2-status-filter" class="form-select form-select-sm" @if($count < 1) disabled @endif>
                    <option value="">{{ __('Show all users status') }}</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option['val'] }}">{{ $option['text'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-5">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end align-items-center">
                    <span class="badge text-bg-secondary d-none cabinet-mon-v2-selection-badge" id="cabinet-mon-v2-selection-badge" @if($count < 1) hidden @endif>0</span>
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            id="cabinet-mon-v2-refresh"
                            title="{{ __('Monitoring v2 refresh list') }}"
                            @if($count < 1) disabled @endif>
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        <span class="d-none d-md-inline ms-1">{{ __('Monitoring v2 refresh list') }}</span>
                    </button>
                    <div class="btn-group btn-group-sm cabinet-mon-v2-view-toggle" role="group" aria-label="{{ __('Monitoring v2 view mode') }}" @if($count < 1) hidden @endif>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cabinet-mon-v2-view-cards"
                                data-view="cards"
                                title="{{ __('Monitoring v2 view cards') }}">
                            <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                            <span class="d-none d-md-inline ms-1">{{ __('Monitoring v2 view cards') }}</span>
                        </button>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cabinet-mon-v2-view-table"
                                data-view="table"
                                title="{{ __('Monitoring v2 view table') }}">
                            <i class="bi bi-table" aria-hidden="true"></i>
                            <span class="d-none d-md-inline ms-1">{{ __('Monitoring v2 view table') }}</span>
                        </button>
                    </div>
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            id="cabinet-mon-v2-select-all"
                            title="{{ __('Select all') }}"
                            @if($count < 1) disabled @endif>
                        <i class="bi bi-check2-square me-1" aria-hidden="true"></i>{{ __('Select all') }}
                    </button>
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            id="cabinet-mon-v2-delete-selected"
                            @if($count < 1) disabled @endif>
                        <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete') }}
                    </button>
                    <a href="{{ route('monitoring.create') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Create project') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
