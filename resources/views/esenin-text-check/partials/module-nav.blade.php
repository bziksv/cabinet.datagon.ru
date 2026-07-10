@php
    $active = $active ?? 'module';
@endphp
@if(auth()->check() && auth()->user()->hasAnyRole(['Super Admin', 'admin']))
    <div class="card shadow-sm cabinet-esenin-nav-card mb-3">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-pills p-2 cabinet-esenin-module-nav mb-0 flex-wrap">
                <li class="nav-item">
                    <a href="{{ route('pages.esenin-text-check') }}"
                       class="nav-link{{ $active === 'module' ? ' active' : '' }}">{{ __('Esenin text check') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('pages.esenin-text-check.settings') }}"
                       class="nav-link{{ $active === 'settings' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            </ul>
        </div>
    </div>
@endif
