@php
    $activeVariant = $activeVariant ?? 1;
@endphp
<div class="cabinet-home-layout-switcher d-flex flex-wrap gap-2 mb-3" role="group" aria-label="{{ __('Home layout') }}">
    <a href="{{ route('home') }}"
       class="btn btn-sm {{ $activeVariant === 1 ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="bi bi-grid-3x3-gap me-1"></i>{{ __('Layout cards') }}
    </a>
    <a href="{{ route('home.variant2') }}"
       class="btn btn-sm {{ $activeVariant === 2 ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="bi bi-list-ul me-1"></i>{{ __('Layout launcher') }}
    </a>
    <a href="{{ route('home.variant3') }}"
       class="btn btn-sm {{ $activeVariant === 3 ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="bi bi-app-indicator me-1"></i>{{ __('Layout hub') }}
    </a>
</div>
