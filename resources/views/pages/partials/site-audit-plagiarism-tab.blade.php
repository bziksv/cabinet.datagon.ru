{{-- Вкладка внешнего антиплагиата: выборочный запуск TextUniqueness --}}
@php
    $plagiarismCandidates = $plagiarismCandidates ?? [];
    $plagiarismCandidatesLazy = !empty($plagiarismCandidatesLazy);
    $plagiarismCandidatesUrl = $plagiarismCandidatesUrl ?? '';
    $plagiarismState = $plagiarismState ?? ['status' => 'idle', 'rows' => [], 'done' => 0, 'total' => 0];
    $plagiarismMaxUrls = (int) ($plagiarismMaxUrls ?? 20);
    $plagiarismWarnBelow = (float) ($plagiarismWarnBelow ?? 70);
    $plagiarismRemaining = $plagiarismRemaining ?? null;
    $plagiarismLimit = $plagiarismLimit ?? null;
    $st = (string) ($plagiarismState['status'] ?? 'idle');
    $running = in_array($st, ['queued', 'running'], true);
@endphp
<div class="tab-pane fade" id="sa-pane-plagiarism" role="tabpanel"
     data-start-url="{{ route('pages.site-audit.plagiarism.start', $crawl->id) }}"
     data-status-url="{{ route('pages.site-audit.plagiarism.status', $crawl->id) }}"
     data-candidates-url="{{ $plagiarismCandidatesUrl }}"
     data-candidates-lazy="{{ $plagiarismCandidatesLazy ? '1' : '0' }}"
     data-max="{{ $plagiarismMaxUrls }}"
     data-warn="{{ $plagiarismWarnBelow }}"
     data-csrf="{{ csrf_token() }}">
    <h5 class="mb-2">Антиплагиат (внешний)</h5>
    <div class="text-secondary small mb-3">
        <p class="mb-2">
            После обхода сами проверяем <strong>примерно 3 страницы</strong>: главную, одну категорию и одну карточку товара или услуги —
            чтобы сразу понять, нет ли копипаста. Это выборочно, не весь сайт.
        </p>
        <ul class="mb-2 ps-3">
            <li>Ниже можно <strong>добрать свои URL</strong> (до {{ $plagiarismMaxUrls }} за раз) и нажать «Проверить выбранные».</li>
            <li>Сравниваем текст с тем, что находится в поиске (модуль «Уникальность текста»).</li>
            <li>Если уникальность ниже <strong>{{ rtrim(rtrim(number_format($plagiarismWarnBelow, 1, '.', ''), '0'), '.') }}%</strong> — страница попадёт в отчёт «Низкая уникальность текста (внешний)».</li>
            <li>Дубли <em>внутри своего сайта</em> — отчёт «Похожие страницы» / «Дубли контента».</li>
        </ul>
        <p class="mb-0">
            Лимит уникальности:
            @if($plagiarismLimit !== null)
                осталось <strong id="sa-plag-remaining">{{ $plagiarismRemaining }}</strong> из <span id="sa-plag-limit">{{ $plagiarismLimit }}</span>.
            @else
                осталось <strong id="sa-plag-remaining">…</strong> из <span id="sa-plag-limit">…</span>
                <span class="text-muted" id="sa-plag-limit-hint">(подгружается)</span>.
            @endif
        </p>
    </div>

    @if($crawl->status !== 'done')
        <div class="alert alert-light border">
            Запуск антиплагиата доступен после завершения проверки.
            Сейчас статус: <strong>{{ $crawl->status === 'aggregating' ? 'агрегация / пост-проверки' : $crawl->status }}</strong>.
            Дождись окончания — вкладка останется здесь, список URL подгрузится сам.
        </div>
    @else
        <div id="sa-plag-candidates-wrap">
            @if($plagiarismCandidatesLazy)
                <div class="alert alert-light border mb-0" id="sa-plag-candidates-loading">Загрузка списка URL…</div>
            @elseif(empty($plagiarismCandidates))
                <div class="alert alert-light border">Нет страниц с достаточным текстом для проверки.</div>
            @else
                @include('pages.partials.site-audit-plagiarism-candidates', [
                    'plagiarismCandidates' => $plagiarismCandidates,
                    'plagiarismMaxUrls' => $plagiarismMaxUrls,
                    'running' => $running,
                ])
            @endif
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
