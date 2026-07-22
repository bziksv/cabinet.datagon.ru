{{-- Вкладка внешнего антиплагиата: выборочный запуск TextUniqueness --}}
@php
    $plagiarismCandidates = $plagiarismCandidates ?? [];
    $plagiarismState = $plagiarismState ?? ['status' => 'idle', 'rows' => [], 'done' => 0, 'total' => 0];
    $plagiarismMaxUrls = (int) ($plagiarismMaxUrls ?? 10);
    $plagiarismWarnBelow = (float) ($plagiarismWarnBelow ?? 70);
    $plagiarismRemaining = $plagiarismRemaining ?? null;
    $plagiarismLimit = $plagiarismLimit ?? null;
    $st = (string) ($plagiarismState['status'] ?? 'idle');
    $running = in_array($st, ['queued', 'running'], true);
@endphp
<div class="tab-pane fade" id="sa-pane-plagiarism" role="tabpanel"
     data-start-url="{{ route('pages.site-audit.plagiarism.start', $crawl->id) }}"
     data-status-url="{{ route('pages.site-audit.plagiarism.status', $crawl->id) }}"
     data-max="{{ $plagiarismMaxUrls }}"
     data-warn="{{ $plagiarismWarnBelow }}"
     data-csrf="{{ csrf_token() }}">
    <h5 class="mb-2">Антиплагиат (внешний)</h5>
    <p class="text-secondary small mb-3">
        Не входит в каждый краул: выбираете URL → проверка уникальности текста через поиск фрагментов (модуль «Уникальность текста»).
        Внутренние дубли посадочных — отчёт «Плагиат lite» в SEO.
        Порог замечания: уникальность &lt; {{ rtrim(rtrim(number_format($plagiarismWarnBelow, 1, '.', ''), '0'), '.') }}%.
        Макс. {{ $plagiarismMaxUrls }} URL за запуск.
        @if($plagiarismLimit !== null)
            Остаток лимита уникальности: <strong id="sa-plag-remaining">{{ $plagiarismRemaining }}</strong> / {{ $plagiarismLimit }}.
        @else
            Лимит уникальности не задан на тарифе (без списания).
        @endif
    </p>

    @if($crawl->status !== 'done')
        <div class="alert alert-light border">Доступно после завершения краула.</div>
    @elseif(empty($plagiarismCandidates))
        <div class="alert alert-light border">Нет страниц с достаточным текстом для проверки.</div>
    @else
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-landings">Только посадочные</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="sa-plag-clear">Снять выбор</button>
            <span class="small text-muted" id="sa-plag-selected">Выбрано: 0 / {{ $plagiarismMaxUrls }}</span>
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="sa-plag-run" {{ $running ? 'disabled' : '' }}>
                {{ $running ? 'Проверка…' : 'Проверить выбранные' }}
            </button>
        </div>

        <div class="cabinet-sa-table-wrap mb-3" style="max-height:320px;overflow:auto">
            <table class="table table-sm mb-0" id="sa-plag-table">
                <thead class="thead-light">
                <tr>
                    <th style="width:36px"></th>
                    <th>URL</th>
                    <th>Title</th>
                    <th>Слов</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($plagiarismCandidates as $c)
                    <tr>
                        <td>
                            <input type="checkbox" class="sa-plag-cb" value="{{ $c['url'] }}"
                                   data-landing="{{ !empty($c['is_landing']) ? '1' : '0' }}"
                                {{ $running ? 'disabled' : '' }}>
                        </td>
                        <td class="small"><a href="{{ $c['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($c['url'], 70) }}</a></td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($c['title'] ?? '—', 50) }}</td>
                        <td>{{ (int) ($c['word_count'] ?? 0) }}</td>
                        <td>@if(!empty($c['is_landing']))<span class="badge bg-info text-dark">посадочная</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div id="sa-plag-progress" class="mb-2" style="{{ $running ? '' : 'display:none' }}">
        <div class="small text-muted mb-1">
            Прогресс: <span id="sa-plag-progress-label">{{ (int) ($plagiarismState['done'] ?? 0) }} / {{ (int) ($plagiarismState['total'] ?? 0) }}</span>
            · статус <span id="sa-plag-status">{{ $st }}</span>
        </div>
        <div class="cabinet-sa-progress">
            @php
                $pt = max(1, (int) ($plagiarismState['total'] ?? 1));
                $pd = (int) ($plagiarismState['done'] ?? 0);
                $pp = min(100, (int) round(100 * $pd / $pt));
            @endphp
            <div class="cabinet-sa-progress__bar" id="sa-plag-progress-bar" style="width: {{ $pp }}%"></div>
        </div>
    </div>

    <div id="sa-plag-error" class="alert alert-warning" style="{{ empty($plagiarismState['error']) ? 'display:none' : '' }}">
        {{ $plagiarismState['error'] ?? '' }}
    </div>

    <div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px">
        <h6 class="mb-0">Результаты</h6>
        <a class="btn btn-sm btn-outline-primary" id="sa-plag-report-link"
           href="{{ route('pages.site-audit.report.show', ['id' => $crawl->id, 'code' => 'landing_plagiarism_external']) }}"
           style="{{ empty($plagiarismState['rows']) ? 'display:none' : '' }}">
            Отчёт findings
        </a>
        <span class="small text-muted" id="sa-plag-cost">
            @if(!empty($plagiarismState['cost_spent']))
                Списано зондов: {{ (int) $plagiarismState['cost_spent'] }}
            @endif
        </span>
    </div>

    <div class="cabinet-sa-table-wrap">
        <table class="table table-sm mb-0">
            <thead class="thead-light">
            <tr>
                <th>URL</th>
                <th>Уник. %</th>
                <th>Совпадения</th>
                <th>Источники</th>
                <th>Ошибка</th>
            </tr>
            </thead>
            <tbody id="sa-plag-results">
            @forelse(($plagiarismState['rows'] ?? []) as $row)
                <tr class="{{ isset($row['uniqueness_pct']) && $row['uniqueness_pct'] < $plagiarismWarnBelow ? 'table-warning' : '' }}">
                    <td class="small"><a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($row['url'], 60) }}</a></td>
                    <td>{{ isset($row['uniqueness_pct']) ? $row['uniqueness_pct'] . '%' : '—' }}</td>
                    <td>{{ isset($row['matched_pct']) ? $row['matched_pct'] . '%' : '—' }}</td>
                    <td class="small">
                        @foreach(array_slice($row['sources'] ?? [], 0, 2) as $src)
                            <div><a href="{{ $src['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($src['url'], 40) }}</a>
                                ({{ $src['overlap_pct'] ?? 0 }}%)</div>
                        @endforeach
                        @if(empty($row['sources'])) — @endif
                    </td>
                    <td class="small text-danger">{{ $row['error'] ?? '' }}</td>
                </tr>
            @empty
                <tr id="sa-plag-empty"><td colspan="5" class="text-muted small">Ещё не запускали</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
