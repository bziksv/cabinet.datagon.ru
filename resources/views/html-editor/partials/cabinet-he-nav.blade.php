@php
    $backUrl = $backUrl ?? route('HTML.editor');
    $backLabel = $backLabel ?? __('My projects');
@endphp

<nav class="cabinet-he-nav mb-3" aria-label="{{ __('HTML editor navigation') }}">
    <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary cabinet-he-nav-back">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ $backLabel }}
    </a>
</nav>
