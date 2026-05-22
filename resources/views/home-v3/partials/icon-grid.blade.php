@if(count($modules) === 0)
    <div class="card shadow-sm">
        <div class="card-body text-center text-secondary py-5">
            <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
            <p class="mb-0">{{ __('No modules are available for your account.') }}</p>
        </div>
    </div>
@else
    <div class="row g-2 g-md-3" id="cabinet-home-v3-grid">
        @foreach($modules as $module)
            <div class="col-4 col-sm-3 col-md-2"
                 data-cabinet-v3-module-title="{{ $module['title'] }}">
                <a href="{{ $module['link'] }}"
                   class="cabinet-home-v3-tile"
                   style="--cabinet-module-accent: {{ $module['color'] }};"
                   data-project-id="{{ $module['id'] }}"
                   title="{{ $module['description'] ?: $module['title'] }}"
                   @if($module['external']) target="_blank" rel="noopener noreferrer" @endif>
                    <span class="cabinet-home-v3-tile__icon" aria-hidden="true">
                        {!! $module['icon'] !!}
                    </span>
                    <span class="cabinet-home-v3-tile__title">{{ $module['title'] }}</span>
                    @if($module['external'])
                        <span class="badge text-bg-light border mt-1" style="font-size: 0.65rem;">{{ __('External') }}</span>
                    @endif
                </a>
            </div>
        @endforeach
    </div>
    <p id="cabinet-home-v3-grid-empty" class="text-center text-secondary small py-4 d-none mb-0">
        {{ __('No modules match your search.') }}
    </p>
@endif
