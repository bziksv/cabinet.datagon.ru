{{-- Тело отчёта (help + filters + table/groups + pagination). --}}
@include('pages.partials.site-audit-report-help')
@php
    $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
    $probeSkipped = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'skipped';
    $isIndexMismatch = in_array($code ?? '', ['index_count_mismatch', 'index_url_missing'], true);
@endphp
@if($probeSkipped && ! $isIndexMismatch)
    <div class="alert alert-warning border small mb-3 cabinet-sa-probe-cta">
        <strong>Проверка не запускалась</strong>
        — {{ \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probeStatus['reason'] ?? null) }}.
        Зелёный «0» здесь не значит «всё ок»: отчёт пустой, потому что пробу ещё не гоняли.
        @if(!empty($probeStatus['can_run']) && empty($isPublic))
            <form method="POST" action="{{ route('pages.site-audit.probe.run', $crawl->id) }}" class="d-inline-block mt-2">
                @csrf
                <input type="hidden" name="probe" value="{{ $probeStatus['probe'] }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <button type="submit" class="btn btn-sm btn-primary"
                        data-cabinet-confirm="Запустить «{{ $probeStatus['title'] }}» по этой проверке? Может занять минуту и потратить API/XML-бюджет."
                        data-cabinet-confirm-title="Запустить проверку"
                        data-cabinet-confirm-ok="Запустить">
                    Запустить {{ $probeStatus['title'] }}
                </button>
            </form>
        @endif
    </div>
@endif
@if($isIndexMismatch && empty($isPublic))
    @php
        $wm = is_array($serpIndexWebmaster ?? null) ? $serpIndexWebmaster : [];
        $wmReady = !empty($wm['ready']);
        $wmConnected = !empty($wm['connected']);
        $wmDomain = (string) ($wm['domain'] ?? ($crawl->project->domain ?? ''));
        $wmMax = (int) config('site_audit.serp_index_webmaster_max', 50000);
        $deepDone = is_array($serpIndexDeep ?? null)
            && (
                ($serpIndexDeep['source'] ?? '') === 'webmaster'
                || ($serpIndexDeep['mode'] ?? '') === 'webmaster_list'
                || isset($serpIndexDeep['serp_count'])
            );
        $returnUrl = route('pages.site-audit.report.show', [$crawl->id, $code]);
        $wmConnectUrl = route('yandex-webmaster.connect', [
            'domain' => $wmDomain,
            'return' => $returnUrl,
        ]);
    @endphp
    <div class="alert {{ $deepDone ? 'alert-success' : 'alert-warning' }} border small mb-3 cabinet-sa-probe-cta">
        <div class="mb-2">
            Сверка списка «в поиске» Вебмастера с краулом выполняется <strong>автоматически при аудите</strong>
            (этап агрегации), если хост привязан.
        </div>

        @if($wmReady)
            <div class="mb-2">
                <strong>Яндекс.Вебмастер подключён</strong>
                — хост <code>{{ $wm['host_id'] }}</code>
                (до {{ number_format($wmMax, 0, '', ' ') }} URL).
            </div>
        @elseif($wmConnected)
            <div class="mb-2">
                <strong>Вебмастер подключён, хост не привязан</strong>
                к домену <code>{{ $wmDomain }}</code>.
                Привяжите сайт на <a href="{{ url('/#webmaster') }}">главной</a>
                — иначе в следующем аудите сверка будет пропущена.
            </div>
        @else
            <div class="mb-2">
                <strong>Подключите Яндекс.Вебмастер</strong>
                и привяжите хост к <code>{{ $wmDomain !== '' ? $wmDomain : 'домену' }}</code>
                до запуска проверки.
                <a class="btn btn-sm btn-outline-primary ms-1" href="{{ $wmConnectUrl }}">Подключить Вебмастер</a>
            </div>
        @endif

        @if($deepDone)
            <strong>Сверка по этой проверке:</strong>
            {{ (int) ($serpIndexDeep['serp_count'] ?? 0) }} URL
            @if(isset($serpIndexDeep['found']))
                (в поиске ~{{ (int) $serpIndexDeep['found'] }})
            @endif
            · совпало
            {{ (int) ($serpIndexDeep['matched'] ?? 0) }}/{{ (int) ($serpIndexDeep['crawl_count'] ?? 0) }}
            @if(!empty($serpIndexDeep['truncated']))
                <span class="text-muted">· список Вебмастера обрезан</span>
            @endif
            @php
                $missingN = (int) ($serpIndexDeep['missing_in_index'] ?? 0);
                $extraN = (int) ($serpIndexDeep['extra_in_index'] ?? 0);
            @endphp
            @if($missingN > 0)
                <div class="mt-2 mb-0">
                    В крауле нет в индексе Яндекса: <strong>{{ $missingN }}</strong>
                    (без robots/noindex) —
                    <a href="{{ route('pages.site-audit.report.show', [$crawl->id, 'index_url_missing']) }}">
                        полный список URL
                    </a>
                </div>
            @endif
            @if($extraN > 0)
                <div class="mt-1 mb-0 text-muted">В индексе нет в крауле: {{ $extraN }} (фрагмент в деталях сводки).</div>
            @endif
        @elseif($probeSkipped)
            <div class="mb-2 text-muted">
                В этой проверке сверка ещё не выполнялась
                ({{ \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probeStatus['reason'] ?? null) }}).
                Запустите новый аудит с привязанным Вебмастером или пересверьте ниже.
            </div>
        @endif

        @if($wmReady && empty($isPublic))
            <form method="POST" action="{{ route('pages.site-audit.probe.run', $crawl->id) }}" class="d-inline-block mt-2">
                @csrf
                <input type="hidden" name="probe" value="serp_index">
                <input type="hidden" name="mode" value="deep">
                <input type="hidden" name="engine" value="yandex">
                <input type="hidden" name="code" value="{{ $code }}">
                <button type="submit" class="btn btn-sm btn-outline-primary"
                        data-cabinet-confirm="Повторно снять список из Вебмастера и сверить с краулом #{{ $crawl->id }}?"
                        data-cabinet-confirm-title="Пересверить"
                        data-cabinet-confirm-ok="Пересверить">
                    Пересверить сейчас
                </button>
            </form>
        @endif
    </div>
@endif
@include('pages.partials.site-audit-report-filters')

@if(!empty($groupable))
    <div class="cabinet-sa-view-toggle mb-3">
        <span class="cabinet-sa-view-toggle__label">Вид:</span>
        <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
           title="Одинаковые проблемы сгруппировать вместе (удобно для дублей)">Группы</a>
        <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
           href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
           title="Просто список всех URL по одной строке">Список</a>
        @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
            <span class="text-muted small ms-2">групп: {{ $groupTotal }} · URL: {{ $total }}</span>
        @endif
    </div>
@endif

@if(!empty($canNote))
    <div class="alert alert-light border small mb-3 cabinet-sa-note-legend">
        <strong>Комментарии и «Исправлено»</strong> —
        сохраняются <u>навсегда для этого проекта</u> (не только для текущей проверки).
        Привязка: тип ошибки + URL. После нового съема комментарий и статус останутся.
        «Исправлено» прячет строку из счётчиков, пока не нажмёте «Открыть» или «Показать исправленные».
    </div>
@endif

@if(!empty($groupable) && ($viewMode ?? '') === 'groups')
    <div class="cabinet-sa-dup-groups">
        @forelse($groups as $gi => $group)
            @php $tone = $gi % 6; @endphp
            <div class="cabinet-sa-dup-group cabinet-sa-dup-group--t{{ $tone }}">
                <div class="cabinet-sa-dup-group__head">
                    <span class="cabinet-sa-dup-group__count">{{ (int) $group['size'] }} стр.</span>
                    <div class="cabinet-sa-dup-group__label">{{ $group['label'] }}</div>
                </div>
                <ul class="cabinet-sa-dup-group__urls">
                    @foreach($group['urls'] as $u)
                        <li>
                            <a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="text-muted px-3 py-3">
                @if(!empty($probeSkipped))
                    Находок нет — проверка ещё не запускалась. Нажмите «Запустить» выше.
                @else
                    Находок нет — проверка выполнена, замечаний по этому отчёту нет.
                @endif
            </div>
        @endforelse
    </div>
@else
    @php
        $colspan = 3;
        if (!empty($showReferrers)) { $colspan++; }
        if (!empty($canIgnore)) { $colspan++; }
        if (!empty($canNote)) { $colspan++; }
        $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
        $isBrokenTarget = !empty($showReferrers) && ! $isRedirectReport;
        $urlTip = $isRedirectReport
            ? "URL, который сам отвечает редиректом.\nГде на него ссылаются — колонка «Откуда ссылаются» (меню, HTML, sitemap)."
            : ($isBrokenTarget
                ? "Это URL, который сам ответил ошибкой при обходе (404 и т.п.).\nНе путать со страницей, где стоит ссылка — она в колонке «Откуда ссылаются»."
                : "Адрес страницы с проблемой.\nНажмите ссылку — откроется сайт в новой вкладке.");
        $urlColTitle = $isRedirectReport
            ? 'URL с редиректом'
            : ($isBrokenTarget ? 'Битый URL (цель)' : 'Адрес страницы с проблемой');
        $urlColLabel = $isRedirectReport
            ? 'URL'
            : ($isBrokenTarget ? 'Битый URL' : 'URL');
        $refColTip = $isRedirectReport
            ? "Откуда URL попал в проверка: sitemap.xml, посев, главная или страница со ссылкой."
            : "Откуда URL попал в проверка или страницы со ссылкой.";
    @endphp
    <div class="cabinet-sa-table-wrap">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:{{ !empty($showReferrers) ? '28%' : '38%' }}" title="{{ $urlColTitle }}">
                    {{ $urlColLabel }}
                    @include('pages.partials.site-audit-tip', ['tip' => $urlTip])
                </th>
                <th title="Насколько срочно чинить: Грубые → Прочие → Предупреждения → Инфо.">
                    Приоритет
                    @include('pages.partials.site-audit-tip', ['tip' => "Срочность.\nГрубые — чинить в первую очередь.\nИнфо — просто знать."])
                </th>
                <th title="Коротко: что именно не так (код ответа, дубль и т.п.).">
                    Детали
                    @include('pages.partials.site-audit-tip', ['tip' => "Кратко что не так: код ответа, какой дубль, какой запрос и т.д."])
                </th>
                @if(!empty($showReferrers))
                    <th style="width:28%" title="Откуда URL попал в очередь проверки">
                        Откуда
                        @include('pages.partials.site-audit-tip', [
                            'tip' => $refColTip,
                        ])
                    </th>
                @endif
                @if(!empty($canNote))
                    <th style="width:220px" title="Ваша заметка по этой ошибке. Хранится в проекте навсегда.">
                        Комментарий / статус
                        @include('pages.partials.site-audit-tip', [
                            'tip' => "Ваша заметка к этой ошибке.\nСохраняется навсегда в проекте (не только в этой проверке).\n«Исправлено» — спрятать из счётчиков, пока не откроете снова.",
                            'tipSide' => 'left',
                        ])
                    </th>
                @endif
                @if(!empty($canIgnore))
                    <th style="width:90px" title="Игнор = «это не ошибка, больше не считай» для проекта.">
                        Игнор
                        @include('pages.partials.site-audit-tip', [
                            'tip' => "Игнор — сказать системе «это не ошибка».\nТоже навсегда для проекта, пока не нажмёте «Вернуть».",
                            'tipSide' => 'left',
                        ])
                    </th>
                @endif
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $isIgn = !empty($ignoredMap[(int) ($row->id ?? 0)]);
                    $note = $notesMap[(int) ($row->id ?? 0)] ?? null;
                    $isFixed = is_array($note) && (($note['status'] ?? '') === 'fixed');
                    $noteComment = is_array($note) ? (string) ($note['comment'] ?? '') : '';
                    $rowMeta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                    $referrers = is_array($rowMeta['referrers'] ?? null) ? $rowMeta['referrers'] : [];
                    $referrerCount = (int) ($rowMeta['referrer_count'] ?? count($referrers));
                @endphp
                <tr class="{{ $isIgn ? 'cabinet-sa-row--ignored' : '' }}{{ $isFixed ? ' cabinet-sa-row--fixed' : '' }}">
                    <td class="cabinet-sa-url">
                        <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer">{{ $row->url }}</a>
                        @if($isIgn)
                            <span class="badge text-bg-light border ms-1">игнор</span>
                        @endif
                        @if($isFixed)
                            <span class="badge text-bg-success ms-1">исправлено</span>
                        @endif
                    </td>
                    <td>{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($row->severity) }}</td>
                    <td class="small">
                        @php
                            $detailsHtml = \App\Services\SiteAudit\SiteAuditFindingPresenter::metaDetailsHtml(
                                $row->code ?? $code,
                                $row->meta_json,
                                $row->url
                            );
                        @endphp
                        @if($detailsHtml !== null)
                            {!! $detailsHtml !!}
                        @else
                            {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::metaLine($row->code ?? $code, $row->meta_json, $row->url) }}
                        @endif
                    </td>
                    @if(!empty($showReferrers))
                        <td class="small">
                            @php
                                $originLabel = trim((string) ($rowMeta['origin_label'] ?? ''));
                                $discoveredVia = trim((string) ($rowMeta['discovered_via'] ?? ''));
                                $discoveredFrom = trim((string) ($rowMeta['discovered_from'] ?? ''));
                                if ($discoveredVia === '' && $discoveredFrom === '' && $referrerCount > 0) {
                                    $discoveredVia = 'link';
                                    $discoveredFrom = trim((string) ($referrers[0] ?? ''));
                                }
                            @endphp
                            @if($discoveredVia === 'link' && $discoveredFrom !== '')
                                <div class="fw-semibold mb-1">страница:</div>
                                <a href="{{ $discoveredFrom }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($discoveredFrom, 70) }}</a>
                            @elseif(in_array($discoveredVia, ['sitemap', 'seed', 'home'], true) && $originLabel !== '')
                                @php
                                    $sitemapHref = trim((string) ($rowMeta['sitemap_href'] ?? ''));
                                    $isSitemapOrigin = $discoveredVia === 'sitemap'
                                        || ! empty($rowMeta['from_sitemap'])
                                        || stripos($originLabel, 'sitemap') !== false;
                                @endphp
                                <div class="fw-semibold">
                                    @if($isSitemapOrigin && $sitemapHref !== '')
                                        <a href="{{ $sitemapHref }}" target="_blank" rel="noopener noreferrer">{{ $originLabel }}</a>
                                    @elseif($discoveredVia === 'home' && !empty($project->domain))
                                        <a href="https://{{ $project->domain }}/" target="_blank" rel="noopener noreferrer">{{ $originLabel }}</a>
                                    @else
                                        {{ $originLabel }}
                                    @endif
                                </div>
                            @elseif($originLabel !== '')
                                <div class="fw-semibold">{{ $originLabel }}</div>
                            @elseif($referrerCount === 0)
                                <span class="text-muted">источник не найден</span>
                            @endif
                            @if($referrerCount > 0 && !($discoveredVia === 'link' && $discoveredFrom !== '' && $referrerCount === 1 && ($referrers[0] ?? '') === $discoveredFrom))
                                <ul class="list-unstyled mb-0 cabinet-sa-referrers mt-1">
                                    @foreach(array_slice($referrers, 0, 5) as $ref)
                                        @if($discoveredVia === 'link' && $ref === $discoveredFrom)
                                            @continue
                                        @endif
                                        <li><a href="{{ $ref }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($ref, 55) }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    @endif
                    @if(!empty($canNote) && !empty($row->id))
                        <td class="cabinet-sa-note-cell">
                            <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-note-form">
                                @csrf
                                <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                <textarea name="comment" rows="2" class="form-control form-control-sm"
                                          placeholder="Напишите заметку… (останется в проекте после нового съема)"
                                          title="Сохраняется навсегда для проекта: эта ошибка + этот URL">{{ $noteComment }}</textarea>
                                <div class="cabinet-sa-note-actions">
                                    <button type="submit" name="status" value="open" class="btn btn-link btn-sm p-0"
                                            title="Записать текст заметки">Сохранить</button>
                                    @if($isFixed)
                                        <button type="submit" name="status" value="open" class="btn btn-link btn-sm p-0"
                                                title="Снова показывать в счётчиках">Открыть</button>
                                    @else
                                        <button type="submit" name="status" value="fixed" class="btn btn-link btn-sm p-0 text-success"
                                                title="Пометить исправленным и спрятать из счётчиков (навсегда в проекте, пока не откроете)">Исправлено</button>
                                    @endif
                                </div>
                            </form>
                        </td>
                    @elseif(!empty($canNote))
                        <td></td>
                    @endif
                    @if(!empty($canIgnore) && !empty($row->id))
                        <td class="text-end">
                            @if($isIgn)
                                <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                    <button type="submit" class="btn btn-link btn-sm p-0" title="Снова учитывать эту ошибку">Вернуть</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-secondary" title="Не считать ошибкой (навсегда для проекта)">Игнор</button>
                                </form>
                            @endif
                        </td>
                    @elseif(!empty($canIgnore))
                        <td></td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $colspan }}" class="text-secondary px-3 py-3">
                    @if(!empty($probeSkipped))
                        Находок нет — проверка ещё не запускалась. Нажмите «Запустить» выше.
                    @else
                        Находок нет — проверка выполнена, замечаний по этому отчёту нет.
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif

@include('pages.partials.site-audit-pager', [
    'page' => $page,
    'pages' => $pages,
    'total' => $total ?? null,
])
