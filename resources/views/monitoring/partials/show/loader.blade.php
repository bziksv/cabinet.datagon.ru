@php
    $loaderSize = $size ?? '';
    $loaderLabel = $label ?? '';
@endphp
<div class="cabinet-mon-loader{{ $loaderSize === 'sm' ? ' cabinet-mon-loader--sm' : '' }}" role="status" aria-live="polite">
    <i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i>
    @if($loaderLabel !== '')
        <span class="cabinet-mon-loader__label">{{ $loaderLabel }}</span>
    @endif
</div>
