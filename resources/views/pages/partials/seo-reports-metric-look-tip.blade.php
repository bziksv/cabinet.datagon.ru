{{-- Миниатюра «как будет в отчёте» рядом с галочкой показателя --}}
@php
    $preview = $metric['preview'] ?? 'table';
    $sample = (string) ($metric['sample'] ?? '');
@endphp
<span class="cabinet-sr-metric-tip" tabindex="0" data-sr-metric-tip>
    <button type="button" class="cabinet-sr-metric-tip__btn" aria-label="{{ __('How it looks in the report') }}">?</button>
    <span class="cabinet-sr-metric-tip__pop" role="tooltip">
        <span class="cabinet-sr-metric-tip__pop-head">{{ __('How it looks in the report') }}</span>
        <span class="cabinet-sr-metric-tip__mock is-{{ $preview }}">
            @if($preview === 'phrases')
                <span class="cabinet-sr-metric-tip__mock-title">{{ $metric['label'] }}</span>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('Query') }}</th>
                        <th>{{ __('Was') }}</th>
                        <th>{{ __('Became') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $line = $sample !== '' ? $sample : "запрос\t12 → 8";
                        if (strpos($line, '→') !== false) {
                            [$q, $pos] = array_pad(explode("\t", $line, 2), 2, '');
                            [$from, $to] = array_pad(preg_split('/\s*→\s*/u', $pos) ?: [], 2, '—');
                        } else {
                            $q = $line; $from = '—'; $to = '—';
                        }
                    @endphp
                    <tr>
                        <td>{{ $q }}</td>
                        <td>{{ trim($from) }}</td>
                        <td class="is-hl">{{ trim($to) }}</td>
                    </tr>
                    <tr>
                        <td>…</td>
                        <td>…</td>
                        <td>…</td>
                    </tr>
                    </tbody>
                </table>
            @elseif($preview === 'kpi')
                <span class="cabinet-sr-metric-tip__kpi">
                    <em>{{ $metric['label'] }}</em>
                    <strong>{{ $sample !== '' ? $sample : '1 240' }}</strong>
                </span>
            @elseif($preview === 'chart')
                <span class="cabinet-sr-metric-tip__mock-title">{{ $metric['label'] }}</span>
                <span class="cabinet-sr-metric-tip__bars" aria-hidden="true">
                    <i style="height:35%"></i><i style="height:48%"></i><i style="height:42%"></i>
                    <i style="height:60%"></i><i style="height:55%"></i><i style="height:72%"></i>
                    <i style="height:68%"></i><i style="height:80%"></i>
                </span>
            @elseif($preview === 'dynamics')
                <span class="cabinet-sr-metric-tip__dyn">
                    <b class="is-up">↑ 12</b>
                    <b class="is-flat">→ 48</b>
                    <b class="is-down">↓ 7</b>
                </span>
            @elseif($preview === 'baskets')
                <span class="cabinet-sr-metric-tip__chips">
                    <i>TOP-3 <b>8</b></i>
                    <i>TOP-10 <b>42</b></i>
                    <i>TOP-30 <b>91</b></i>
                </span>
            @elseif($preview === 'list')
                <ul>
                    <li>{{ $sample !== '' ? $sample : $metric['label'] }}</li>
                    <li>…</li>
                </ul>
            @elseif($preview === 'text')
                <span class="cabinet-sr-metric-tip__text">{{ __('Comment block in report') }}</span>
            @else
                <span class="cabinet-sr-metric-tip__mock-title">{{ $metric['label'] }}</span>
                <table>
                    <thead><tr><th>A</th><th>B</th></tr></thead>
                    <tbody>
                    @foreach(preg_split("/\n/", $sample !== '' ? $sample : "строка\t42\n…\t…") as $rowLine)
                        @php $cols = preg_split("/\t/", $rowLine) ?: []; @endphp
                        <tr>
                            @foreach($cols as $c)
                                <td>{{ $c }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </span>
    </span>
</span>
