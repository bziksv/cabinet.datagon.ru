@php
    $statusParam = $statusParam ?? null;
    $href = route('support.index', array_filter([
        'status' => $statusParam,
        'q' => $search ?? null,
    ]));
    $active = ($filter ?? 'all') === ($statusParam === null ? 'all' : $statusParam);
@endphp
<div class="col-6 col-md-3 d-flex">
    <a href="{{ $href }}" class="info-box mb-0 flex-fill text-decoration-none {{ $active ? 'cabinet-support-stat--active' : '' }}">
        <span class="info-box-icon {{ $iconClass }} shadow-sm">
            <i class="bi {{ $icon }}"></i>
        </span>
        <div class="info-box-content">
            <span class="info-box-text">{{ $label }}</span>
            <span class="info-box-number">{{ $count }}</span>
        </div>
    </a>
</div>
