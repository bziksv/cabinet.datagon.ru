@php
    $active = $active ?? 'module';
@endphp
@if(auth()->check() && auth()->user()->hasAnyRole(['Super Admin', 'admin']))
    <div class="card shadow-sm cabinet-sa-nav-card mb-3">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-pills p-2 cabinet-sa-module-nav mb-0 flex-wrap">
                <li class="nav-item">
                    <a href="{{ route('pages.site-audit') }}"
                       class="nav-link{{ $active === 'module' ? ' active' : '' }}">Аудит сайта</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('pages.site-audit.admin') }}"
                       class="nav-link{{ $active === 'admin' ? ' active' : '' }}">{{ __('Module administration') }}</a>
                </li>
            </ul>
        </div>
    </div>
@endif
