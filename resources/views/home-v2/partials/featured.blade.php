@if(count($featuredModules) > 0)
    <div class="mb-3">
        <h2 class="h6 text-secondary text-uppercase mb-2">{{ __('Recommended to start') }}</h2>
        <div class="row g-3">
            @foreach($featuredModules as $index => $module)
                <div class="{{ $index === 0 ? 'col-12 col-md-8' : 'col-12 col-md-4' }}">
                    <a href="{{ $module['link'] }}"
                       class="card cabinet-home-v2-featured shadow-sm {{ $index === 0 ? 'cabinet-home-v2-featured--lg' : '' }} position-relative cabinet-home-v2-open"
                       style="--cabinet-module-accent: {{ $module['color'] }};"
                       data-project-id="{{ $module['id'] }}"
                       @if($module['external']) target="_blank" rel="noopener noreferrer" @endif>
                        <span class="cabinet-home-v2-featured__bg" aria-hidden="true"></span>
                        <div class="cabinet-home-v2-featured__body">
                            <span class="cabinet-home-v2-featured__icon" aria-hidden="true">
                                {!! $module['icon'] !!}
                            </span>
                            <h3 class="h5 mb-2">{{ $module['title'] }}</h3>
                            @if($module['description'])
                                <p class="text-secondary small mb-auto pe-lg-4">
                                    {{ \Illuminate\Support\Str::limit($module['description'], $index === 0 ? 160 : 90) }}
                                </p>
                            @endif
                            <span class="btn btn-sm btn-primary mt-3 align-self-start">
                                {{ __('Open') }} <i class="bi bi-arrow-right ms-1"></i>
                            </span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endif
