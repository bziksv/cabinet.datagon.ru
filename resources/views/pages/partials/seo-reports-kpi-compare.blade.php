@php
    $curRaw = $curValue ?? null;
    $prevRaw = $prevValue ?? null;
    $prevText = $prevDisplay ?? null;
    $curText = $curDisplay ?? null;
    $deltaText = $deltaDisplay ?? null;
    $deltaClass = $deltaClass ?? '';
    $hasCompare = $prevRaw !== null && $prevText !== null && $curText !== null;
@endphp
@if($hasCompare)
    @php
        $curAbs = abs((float) $curRaw);
        $prevAbs = abs((float) $prevRaw);
        $scale = max($curAbs, $prevAbs, 0.0001);
        $curPct = max(10, (int) round(100 * $curAbs / $scale));
        $prevPct = max(10, (int) round(100 * $prevAbs / $scale));
        $arrow = '→';
        if (strpos($deltaClass, 'is-up') !== false) {
            $arrow = '↑';
        } elseif (strpos($deltaClass, 'is-down') !== false) {
            $arrow = '↓';
        }
    @endphp
    {{-- Когда сравнение выключено чекбоксом — показываем только текущее значение --}}
    <div class="cabinet-sr-kpi__value cabinet-sr-compare-off-only">{{ $curText }}</div>
    <div class="cabinet-sr-kpi-cmp">
        @if($deltaText !== null && $deltaText !== '')
            <div class="cabinet-sr-kpi-cmp__delta {{ $deltaClass }}">
                <span class="cabinet-sr-kpi-cmp__arrow" aria-hidden="true">{{ $arrow }}</span>
                <span>{{ $deltaText }}</span>
            </div>
        @endif
        <div class="cabinet-sr-kpi-cmp__cols">
            <div class="cabinet-sr-kpi-cmp__col is-prev">
                <span class="cabinet-sr-kpi-cmp__tag">{{ __('Previous period') }}</span>
                <strong class="cabinet-sr-kpi-cmp__num">{{ $prevText }}</strong>
                <span class="cabinet-sr-kpi-cmp__bar" aria-hidden="true">
                    <i style="width: {{ $prevPct }}%"></i>
                </span>
            </div>
            <div class="cabinet-sr-kpi-cmp__col is-cur">
                <span class="cabinet-sr-kpi-cmp__tag">{{ __('Report period') }}</span>
                <strong class="cabinet-sr-kpi-cmp__num">{{ $curText }}</strong>
                <span class="cabinet-sr-kpi-cmp__bar" aria-hidden="true">
                    <i style="width: {{ $curPct }}%"></i>
                </span>
            </div>
        </div>
    </div>
@else
    @if($curText !== null)
        <div class="cabinet-sr-kpi__value">{{ $curText }}</div>
    @endif
    @if($deltaText !== null && $deltaText !== '')
        <div class="cabinet-sr-kpi__delta {{ $deltaClass }}">{{ $deltaText }}</div>
    @endif
@endif
