<div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-list-ul me-1"></i>
            {{ __('All modules') }}
            <span class="badge text-bg-light text-body-secondary border ms-1">{{ count($modules) }}</span>
        </h3>
        <div class="input-group input-group-sm" style="max-width: 16rem;">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search"
                   class="form-control"
                   id="cabinet-home-v2-module-search"
                   placeholder="{{ __('Find a module') }}…"
                   autocomplete="off">
        </div>
    </div>

    @if(count($modules) === 0)
        <div class="card-body text-center text-secondary py-5">
            <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
            <p class="mb-0">{{ __('No modules are available for your account.') }}</p>
        </div>
    @else
        <div class="list-group list-group-flush cabinet-home-v2-list" id="cabinet-home-v2-module-list">
            @forelse($listModules as $module)
                <a href="{{ $module['link'] }}"
                   class="list-group-item list-group-item-action cabinet-home-v2-list-item cabinet-home-v2-open d-flex align-items-center gap-3"
                   style="--cabinet-module-accent: {{ $module['color'] }};"
                   data-cabinet-v2-module-title="{{ $module['title'] }}"
                   data-project-id="{{ $module['id'] }}"
                   @if($module['external']) target="_blank" rel="noopener noreferrer" @endif>
                    <span class="cabinet-home-v2-list__icon" aria-hidden="true">
                        {!! $module['icon'] !!}
                    </span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block fw-semibold text-truncate">{{ $module['title'] }}</span>
                        @if($module['description'])
                            <span class="d-block small text-secondary text-truncate">
                                {{ $module['description'] }}
                            </span>
                        @endif
                    </span>
                    @if($module['external'])
                        <span class="badge text-bg-light border flex-shrink-0">{{ __('External') }}</span>
                    @endif
                    <i class="bi bi-chevron-right text-secondary flex-shrink-0" aria-hidden="true"></i>
                </a>
            @empty
                <div class="list-group-item text-secondary small border-0">
                    {{ __('All modules are shown above.') }}
                </div>
            @endforelse
        </div>
        <div id="cabinet-home-v2-list-empty" class="card-body text-center text-secondary py-4 d-none">
            {{ __('No modules match your search.') }}
        </div>
    @endif
</div>
