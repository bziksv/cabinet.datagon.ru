{{-- Таблица релевантности посадочных (SSR или AJAX) --}}
@php
    $withHistory = collect($relevanceRows)->where('history_id', '!=', null)->count();
    $without = count($relevanceRows) - $withHistory;
@endphp
<div class="small text-muted mb-2">
    Посадочных: {{ count($relevanceRows) }}
    · с анализом: {{ $withHistory }}
    · без анализа: {{ $without }}
</div>
<div class="cabinet-sa-table-wrap">
    <table class="table table-sm mb-0">
        <thead class="thead-light">
        <tr>
            <th>Запрос</th>
            <th>Посадочная</th>
            <th>Баллы</th>
            <th>Покрытие</th>
            <th>Поз.</th>
            <th>Проверка</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($relevanceRows as $row)
            <tr>
                <td class="small">{{ \Illuminate\Support\Str::limit($row['query'], 40) }}</td>
                <td class="small">
                    <a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($row['url'], 55) }}</a>
                </td>
                @if(!empty($row['history_id']))
                    <td>{{ $row['points'] ?? '—' }}</td>
                    <td class="small">
                        @if($row['coverage'] !== null || $row['coverage_tf'] !== null)
                            {{ $row['coverage'] ?? '—' }} / TF {{ $row['coverage_tf'] ?? '—' }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row['position'] ?? '—' }}</td>
                    <td class="small text-muted">{{ $row['last_check'] ?? '—' }}</td>
                    <td class="text-nowrap">
                        @if(!empty($row['history_url']))
                            <a class="btn btn-sm btn-outline-primary" href="{{ $row['history_url'] }}" target="_blank" rel="noopener">Открыть анализ</a>
                        @endif
                        <a class="btn btn-sm btn-outline-secondary" href="{{ $row['analyze_url'] }}" target="_blank" rel="noopener" title="Повторить с prefill">Ещё раз</a>
                    </td>
                @else
                    <td colspan="4" class="small text-muted">Расчёта ещё не было</td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-primary" href="{{ $row['analyze_url'] }}" target="_blank" rel="noopener">Проверить в анализаторе</a>
                    </td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
