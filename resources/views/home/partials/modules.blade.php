<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h2 class="h5 mb-0">
        <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>
        {{ __('Tools and modules') }}
        <span class="badge text-bg-light text-body-secondary border ms-1">{{ count($modules) }}</span>
    </h2>
    <div class="input-group input-group-sm" style="max-width: 18rem;">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search"
               class="form-control"
               id="cabinet-home-module-search"
               placeholder="{{ __('Find a module') }}…"
               autocomplete="off"
               aria-label="{{ __('Find a module') }}">
    </div>
</div>

@if(count($modules) === 0)
    <div class="cabinet-home-empty text-center text-secondary py-5 px-3">
        <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
        <p class="mb-0">{{ __('No modules are available for your account.') }}</p>
    </div>
@else
    <div class="row g-3" id="cabinet-home-modules-grid">
        @foreach($modules as $module)
            <div class="col-12 col-sm-6 col-lg-4 col-xl-3 d-flex"
                 data-cabinet-module-title="{{ $module['title'] }}"
                 data-project-id="{{ $module['id'] }}">
                <div class="card cabinet-home-module-card flex-fill"
                     style="--cabinet-module-accent: {{ $module['color'] }};">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <span class="cabinet-home-module-card__icon" aria-hidden="true">
                                {!! $module['icon'] !!}
                            </span>
                            <div class="min-w-0 flex-grow-1">
                                <h3 class="h6 card-title mb-1 text-break">{{ $module['title'] }}</h3>
                                @if($module['external'])
                                    <span class="badge text-bg-light border">{{ __('External') }}</span>
                                @endif
                            </div>
                        </div>
                        @if($module['description'])
                            <p class="card-text text-secondary small cabinet-home-module-card__desc mb-3">
                                {{ $module['description'] }}
                            </p>
                        @else
                            <p class="card-text text-secondary small mb-3 opacity-50">—</p>
                        @endif
                        <div class="mt-auto">
                            <a href="{{ $module['link'] }}"
                               class="btn btn-primary btn-sm w-100 cabinet-home-module-open"
                               data-track="open_module"
                               @if($module['external']) target="_blank" rel="noopener noreferrer" @endif>
                                {{ __('Open') }}
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div id="cabinet-home-modules-empty" class="cabinet-home-empty text-center text-secondary py-5 px-3 d-none">
        <i class="bi bi-search display-6 d-block mb-2 opacity-50"></i>
        <p class="mb-0">{{ __('No modules match your search.') }}</p>
    </div>
@endif
