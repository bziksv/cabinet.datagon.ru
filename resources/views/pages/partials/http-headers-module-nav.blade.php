@php
    $active = $active ?? 'check';
@endphp
<nav class="cabinet-hh-module-nav card border shadow-sm mb-0" aria-label="{{ __('Http headers nav label') }}">
    <div class="card-body py-2 px-2">
        <ul class="nav nav-pills cabinet-hh-module-nav__list flex-wrap gap-1 mb-0">
            <li class="nav-item">
                <a href="{{ url('/http-headers') }}"
                   class="nav-link{{ $active === 'check' ? ' active' : '' }}"
                   @if($active === 'check') aria-current="page" @endif>
                    <i class="bi bi-globe2 me-1" aria-hidden="true"></i>{{ __('Http headers nav check') }}
                </a>
            </li>
            @hasanyrole('Super Admin|admin')
                <li class="nav-item">
                    <a href="{{ route('pages.headers.settings') }}"
                       class="nav-link{{ $active === 'settings' ? ' active' : '' }}"
                       @if($active === 'settings') aria-current="page" @endif>
                        <i class="bi bi-gear me-1" aria-hidden="true"></i>{{ __('Settings') }}
                    </a>
                </li>
            @endhasanyrole
        </ul>
    </div>
</nav>
