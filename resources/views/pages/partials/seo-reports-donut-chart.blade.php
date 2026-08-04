{{--
  Круговая диаграмма долей (SVG doughnut — без зависимости от layout Chart.js).
  @param list<array{label:string,value:float|int}> $slices
  @param string $title
  @param string|null $unitLabel
--}}
@php
    $slices = is_array($slices ?? null) ? $slices : [];
    $labels = [];
    $values = [];
    foreach ($slices as $s) {
        $lab = trim((string) ($s['label'] ?? ''));
        $val = (float) ($s['value'] ?? 0);
        if ($lab === '' || $val <= 0) {
            continue;
        }
        $labels[] = $lab;
        $values[] = $val;
    }
    $title = (string) ($title ?? '');
    $unitLabel = (string) ($unitLabel ?? '');
    $unitShort = $unitLabel !== '' ? $unitLabel : (string) __('Visits');
    $total = array_sum($values);
    $palette = ['#0d9488', '#f59e0b', '#3b82f6', '#a855f7', '#ef4444', '#64748b', '#14b8a6', '#eab308'];

    $arcs = [];
    if ($total > 0 && $labels !== []) {
        $cx = 100.0;
        $cy = 100.0;
        $rOuter = 88.0;
        $rInner = 54.0;
        $angle = -M_PI_2; // сверху
        foreach ($values as $i => $val) {
            $sweep = ($val / $total) * 2 * M_PI;
            // полный круг одной дугой — иначе SVG arc с large-arc ломается
            if (count($values) === 1 || abs($sweep - 2 * M_PI) < 1e-6) {
                $arcs[] = [
                    'color' => $palette[$i % count($palette)],
                    'label' => $labels[$i],
                    'value' => $val,
                    'pct' => round(100 * $val / $total, 1),
                    'full' => true,
                ];
                break;
            }
            $a0 = $angle;
            $a1 = $angle + $sweep;
            $large = $sweep > M_PI ? 1 : 0;
            $x0o = $cx + $rOuter * cos($a0);
            $y0o = $cy + $rOuter * sin($a0);
            $x1o = $cx + $rOuter * cos($a1);
            $y1o = $cy + $rOuter * sin($a1);
            $x0i = $cx + $rInner * cos($a1);
            $y0i = $cy + $rInner * sin($a1);
            $x1i = $cx + $rInner * cos($a0);
            $y1i = $cy + $rInner * sin($a0);
            $d = sprintf(
                'M %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 0 %.3f %.3f Z',
                $x0o, $y0o, $rOuter, $rOuter, $large, $x1o, $y1o,
                $x0i, $y0i, $rInner, $rInner, $large, $x1i, $y1i
            );
            $arcs[] = [
                'color' => $palette[$i % count($palette)],
                'label' => $labels[$i],
                'value' => $val,
                'pct' => round(100 * $val / $total, 1),
                'd' => $d,
                'full' => false,
            ];
            $angle = $a1;
        }
    }
@endphp
@if($labels !== [] && $total > 0 && $arcs !== [])
    <div class="cabinet-sr-donut" data-sr-donut-chart>
        <div class="cabinet-sr-donut__canvas-wrap">
            <div class="cabinet-sr-donut__tip" data-sr-donut-tip aria-hidden="true"></div>
            <svg class="cabinet-sr-donut__svg" viewBox="0 0 200 200" role="img" aria-label="{{ $title }}">
                @foreach($arcs as $arc)
                    @php
                        $tipTitle = $arc['label'];
                        $tipVal = number_format($arc['value'], 0, ',', ' ')
                            . ' · '
                            . number_format($arc['pct'], 1, ',', ' ')
                            . '%';
                    @endphp
                    @if(!empty($arc['full']))
                        <circle cx="100" cy="100" r="71" fill="none" stroke="{{ $arc['color'] }}" stroke-width="34"
                                data-sr-donut-seg
                                data-tip-title="{{ $tipTitle }}"
                                data-tip-val="{{ $tipVal }}"></circle>
                    @else
                        <path d="{{ $arc['d'] }}" fill="{{ $arc['color'] }}"
                              data-sr-donut-seg
                              data-tip-title="{{ $tipTitle }}"
                              data-tip-val="{{ $tipVal }}"></path>
                    @endif
                @endforeach
            </svg>
            <div class="cabinet-sr-donut__center" aria-hidden="true">
                <strong>{{ number_format($total, 0, ',', ' ') }}</strong>
                <span>{{ $unitShort }}</span>
            </div>
        </div>
        <ul class="cabinet-sr-donut__legend">
            @foreach($labels as $i => $lab)
                @php $pct = round(100 * $values[$i] / $total, 1); @endphp
                <li>
                    <i style="background: {{ $palette[$i % count($palette)] }}"></i>
                    <span class="cabinet-sr-donut__legend-name">{{ $lab }}</span>
                    <b>{{ number_format($values[$i], 0, ',', ' ') }}</b>
                    <em>{{ number_format($pct, 1, ',', ' ') }}%</em>
                </li>
            @endforeach
        </ul>
    </div>
@endif
