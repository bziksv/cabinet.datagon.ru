{{--
  Интерактивный график по дням (Chart.js).
  @param array $series  date => value
  @param string $title
  @param string|null $color  hex, optional
  @param string|null $unitLabel  e.g. «пользователи»
--}}
@php
    $series = is_array($series ?? null) ? $series : [];
    $labels = [];
    $values = [];
    foreach ($series as $date => $val) {
        $labels[] = (string) $date;
        $values[] = (float) $val;
    }
    $title = (string) ($title ?? '');
    $color = (string) ($color ?? '');
    $unitLabel = (string) ($unitLabel ?? '');
    $chartId = 'sr-day-chart-' . substr(md5($title . implode(',', $labels) . implode(',', $values)), 0, 10);
@endphp
@if($labels !== [])
    <div class="cabinet-sr-day-chart" data-sr-day-chart>
        @if($title !== '')
            <div class="cabinet-sr-day-chart__head">
                <h3 class="cabinet-sr-day-chart__title">{{ $title }}</h3>
                @php
                    $sum = array_sum($values);
                    $avg = count($values) > 0 ? $sum / count($values) : 0;
                    $peak = max($values ?: [0]);
                    $peakIdx = array_search($peak, $values, true);
                    $peakDate = $peakIdx !== false ? ($labels[$peakIdx] ?? '') : '';
                @endphp
                <div class="cabinet-sr-day-chart__stats">
                    <span>{{ __('Total') }}: <b>{{ number_format($sum, 0, ',', ' ') }}</b></span>
                    <span>{{ __('Average') }}: <b>{{ number_format($avg, 1, ',', ' ') }}</b></span>
                    @if($peakDate !== '')
                        <span>{{ __('Peak') }}: <b>{{ number_format($peak, 0, ',', ' ') }}</b>
                            <em>{{ \Carbon\Carbon::parse($peakDate)->format('d.m') }}</em>
                        </span>
                    @endif
                </div>
            </div>
        @endif
        <div class="cabinet-sr-day-chart__canvas-wrap">
            <canvas id="{{ $chartId }}"
                    height="220"
                    data-sr-chart-labels='@json($labels)'
                    data-sr-chart-values='@json($values)'
                    data-sr-chart-color="{{ $color }}"
                    data-sr-chart-unit="{{ $unitLabel }}"
                    data-sr-chart-title="{{ $title }}"
                    role="img"
                    aria-label="{{ $title }}"></canvas>
        </div>
        <p class="cabinet-sr-day-chart__hint">{{ __('Hover a bar to see the day value') }}</p>
    </div>
@endif
