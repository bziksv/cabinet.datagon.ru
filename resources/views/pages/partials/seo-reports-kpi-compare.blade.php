@php
    $curRaw = $curValue ?? null;
    $prevRaw = $prevValue ?? null;
    $prevText = $prevDisplay ?? null;
    $deltaText = $deltaDisplay ?? null;
    $deltaClass = $deltaClass ?? '';
@endphp
@if($deltaText !== null && $deltaText !== '')
    <div class="cabinet-sr-kpi__delta {{ $deltaClass }}">{{ $deltaText }}</div>
@endif
@if($prevRaw !== null && $prevText !== null)
    <div class="cabinet-sr-kpi__prev">{{ __('Was') }}: {{ $prevText }}</div>
    @php
        $curAbs = abs((float) $curRaw);
        $prevAbs = abs((float) $prevRaw);
        $scale = max($curAbs, $prevAbs, 0.0001);
    @endphp
    <div class="cabinet-sr-kpi__compare" aria-hidden="true" title="{{ __('Previous period') }} / {{ __('Report period') }}">
        <span class="cabinet-sr-kpi__compare-row">
            <i class="is-prev" style="width: {{ max(4, (int) round(100 * $prevAbs / $scale)) }}%"></i>
        </span>
        <span class="cabinet-sr-kpi__compare-row">
            <i class="is-cur" style="width: {{ max(4, (int) round(100 * $curAbs / $scale)) }}%"></i>
        </span>
    </div>
@endif
