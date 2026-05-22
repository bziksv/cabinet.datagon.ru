@php
    $inline = !empty($inline);
    $trend = $trend ?? null;
@endphp
@if($trend === null)
    <span class="info-box-text small cabinet-ms-trend-muted {{ $inline ? 'ms-1' : 'd-block' }}">—</span>
@else
    @php
        $class = $trend >= 0 ? 'cabinet-ms-trend-up' : 'cabinet-ms-trend-down';
        $icon = $trend >= 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short';
        $prefix = $trend > 0 ? '+' : '';
    @endphp
    <span class="info-box-text small {{ $class }} {{ $inline ? 'ms-1' : 'd-block' }}">
        <i class="bi {{ $icon }}"></i>{{ $prefix }}{{ $trend }}%
    </span>
@endif
