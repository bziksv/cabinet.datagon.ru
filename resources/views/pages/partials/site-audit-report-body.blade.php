{{-- Тело отчёта (help + filters + table/groups + pagination). --}}
@if(!empty($isExternalModule))
    @include('pages.partials.site-audit-external-module')
@else
@include('pages.partials.site-audit-report-help')
@php
    $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
    $probeSkipped = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'skipped';
    $isIndexMismatch = ($code ?? '') === 'index_count_mismatch';
@endphp
@if($probeSkipped && ! $isIndexMismatch)
    <div class="alert alert-warning border small mb-3 cabinet-sa-probe-cta">
        <strong>Проверка не запускалась</strong>
        — {{ \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probeStatus['reason'] ?? null, $probeStatus['probe'] ?? null) }}.
        Зелёный «0» здесь не значит «всё ок»: отчёт пустой, потому что пробу ещё не гоняли.
        @if(($probeStatus['probe'] ?? '') === 'serp_snippets')
            <div class="mt-1 mb-0">
                Сниппеты — <strong>выборка</strong> (посадочные страницы и несколько страниц из обхода), Яндекс и Google.
                В «TITLE ≠ выдаче» попадают только расхождения: совпавшая ПС в таблицу не пишется.
                Нормально снимать <strong>при аудите</strong> (вкл. по умолчанию) или кнопкой ниже.
            </div>
        @endif
        @if(($probeStatus['probe'] ?? '') === 'psi')
            @php
                $psiMaxCta = (int) config('site_audit.psi_max_urls', 20);
                $psiReason = (string) ($probeStatus['reason'] ?? '');
            @endphp
            <div class="mt-1 mb-0">
                @if($psiReason === 'api_quota')
                    Замер пробовали (до {{ $psiMaxCta }} страниц), но Google ответил отказом по квоте.
                    Без ключа PageSpeed API лимит очень маленький. Нужен ключ в настройках сервера
                    или подождать сброса дневной квоты — потом снова «Запустить».
                @else
                    PageSpeed меряет до <strong>{{ $psiMaxCta }}</strong> страниц
                    (не весь сайт), каждую — с телефона и с компьютера.
                    Может занять <strong>10–30+ минут</strong>.
                    В новых аудитах запускается само; здесь можно догнать кнопкой.
                @endif
            </div>
        @endif
        @if(!empty($probeStatus['can_run']) && empty($isPublic))
            @php
                $probeConfirm = ($probeStatus['probe'] ?? '') === 'psi'
                    ? 'Запустить PageSpeed по этой проверке? До ' . (int) config('site_audit.psi_max_urls', 20) . ' страниц × телефон и компьютер, может занять 10–30+ минут.'
                    : 'Запустить «' . ($probeStatus['title'] ?? 'проверку') . '» по этой проверке? Может занять 1–3 минуты и потратить API/XML-бюджет.';
            @endphp
            <form method="POST" action="{{ route('pages.site-audit.probe.run', $crawl->id) }}" class="d-inline-block mt-2"
                  data-cabinet-confirm="{{ $probeConfirm }}"
                  data-cabinet-confirm-title="Запустить проверку"
                  data-cabinet-confirm-ok="Запустить">
                @csrf
                <input type="hidden" name="probe" value="{{ $probeStatus['probe'] }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <button type="submit" class="btn btn-sm btn-primary"
                        data-cabinet-probe-submit>
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
        if (! is_array($serpIndexDeep ?? null) && isset($crawl)) {
            $serpIndexDeep = is_array($crawl->progress_json['serp_index']['deep'] ?? null)
                ? $crawl->progress_json['serp_index']['deep']
                : null;
        }
        $probeRan = is_array($probeStatus ?? null) && ($probeStatus['status'] ?? '') === 'ran';
        $deepDone = $probeRan || (
            is_array($serpIndexDeep ?? null)
            && (
                ($serpIndexDeep['source'] ?? '') === 'webmaster'
                || ($serpIndexDeep['mode'] ?? '') === 'webmaster_list'
                || isset($serpIndexDeep['serp_count'])
                || isset($serpIndexDeep['matched'])
            )
        );
        // Не орём «не выполнялась», если в таблице уже есть строки этой сверки.
        if (! $deepDone && ! empty($rows) && count($rows) > 0) {
            $deepDone = true;
        }
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
                $missingN = (int) ($serpIndexDeep['missing_in_index'] ?? count($rows ?? []));
                $extraN = (int) ($serpIndexDeep['extra_in_index'] ?? 0);
            @endphp
            @if($missingN > 0)
                <div class="mt-2 mb-0">
                    Нет в индексе: <strong>{{ $missingN }}</strong> URL краула
                    (без robots/noindex). В таблице ниже — URL и ПС в «деталях».
                </div>
            @endif
            @if($extraN > 0)
                <div class="mt-1 mb-0 text-muted">В индексе нет в крауле: {{ $extraN }}.</div>
            @endif
        @elseif(!empty($probeSkipped))
            <div class="mb-2 text-muted">
                В этой проверке сверка ещё не выполнялась
                ({{ \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probeStatus['reason'] ?? null, $probeStatus['probe'] ?? null) }}).
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
        @if(!empty($isHtmlErrorReport))
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
               title="Одинаковые ошибки вместе — видно сквозной шаблон (шапка/подвал)">По ошибкам</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
               title="Список всех URL по одной строке">По страницам</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">паттернов: {{ number_format((int) $groupTotal, 0, '', ' ') }} · URL: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @elseif(!empty($isLinkInvertedReport))
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
               title="Одинаковые исходящие вместе — видно общий блок (шапка/подвал/соцсети)">По ссылкам</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
               title="Каждая страница со своим списком исходящих">По страницам</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">целей: {{ number_format((int) $groupTotal, 0, '', ' ') }} · стр.: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @else
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'groups' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}"
               title="Одинаковые проблемы сгруппировать вместе (удобно для дублей)">Группы</a>
            <a class="cabinet-sa-view-toggle__btn {{ ($viewMode ?? '') === 'list' ? 'is-active' : '' }}"
               href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}"
               title="Просто список всех URL по одной строке">Список</a>
            @if(($viewMode ?? '') === 'groups' && !empty($groupTotal))
                <span class="text-muted small ms-2">групп: {{ number_format((int) $groupTotal, 0, '', ' ') }} · URL: {{ number_format((int) $total, 0, '', ' ') }}</span>
            @endif
        @endif
    </div>
@endif

@if(!empty($htmlSitewide) && is_array($htmlSitewide))
    <div class="alert alert-warning border small mb-3 cabinet-sa-html-sitewide">
        @if(!empty($isLinkInvertedReport))
            <strong>Скорее общий блок</strong>
            (шапка / подвал / соцсети) —
            одна и та же исходящая на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            @if(!empty($htmlSitewide['label']))
                <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            @endif
            <div class="mt-1 mb-0">
                Не разбирайте каждую страницу: уберите или разметьте ссылку в общем шаблоне один раз.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Смотреть по ссылкам →</a>
                @endif
            </div>
        @else
            <strong>Скорее сквозной шаблон</strong>
            (шапка / подвал / layout) —
            одна и та же ошибка на
            <strong>{{ number_format((int) $htmlSitewide['pages'], 0, '', ' ') }}</strong>
            из {{ number_format((int) $htmlSitewide['total'], 0, '', ' ') }} стр.
            ({{ (int) $htmlSitewide['pct'] }}%):
            <code class="cabinet-sa-html-sitewide__code">{{ $htmlSitewide['label'] }}</code>.
            <div class="mt-1 mb-0">
                Не правьте каждую страницу: найдите общий include и почините один раз —
                ошибка уйдёт с остальных URL после нового съема.
                @if(($viewMode ?? '') === 'list')
                    <a href="{{ request()->fullUrlWithQuery(['view' => 'groups', 'page' => 1]) }}">Смотреть по ошибкам →</a>
                @endif
            </div>
        @endif
    </div>
@endif

@if(!empty($canNote))
    <div class="alert alert-light border small mb-3 cabinet-sa-note-legend">
        Заметка / Исправлено / Игнор — навсегда для проекта (тип ошибки + URL). Наведите на «?» у кнопки.
    </div>
@endif

@if(!empty($groupable) && ($viewMode ?? '') === 'groups')
    <div class="cabinet-sa-dup-groups">
        @forelse($groups as $gi => $group)
            @php $tone = $gi % 6; @endphp
            <div class="cabinet-sa-dup-group cabinet-sa-dup-group--t{{ $tone }}{{ !empty($group['likely_template']) ? ' cabinet-sa-dup-group--template' : '' }}">
                <div class="cabinet-sa-dup-group__head">
                    <span class="cabinet-sa-dup-group__count">{{ number_format((int) $group['size'], 0, '', ' ') }} стр.</span>
                    @if(!empty($group['likely_template']))
                        <span class="cabinet-sa-dup-group__badge" title="{{ !empty($isLinkInvertedReport) ? 'Одинаковая исходящая на многих URL — почти наверняка общий блок' : 'Одинаковая ошибка на многих URL — почти наверняка общий шаблон' }}">{{ !empty($isLinkInvertedReport) ? 'общий блок' : 'сквозной' }}</span>
                    @endif
                    <div class="cabinet-sa-dup-group__label">
                        @if(!empty($group['href']))
                            @if(!empty($group['host']))
                                <span class="text-secondary me-1">{{ $group['host'] }}</span>
                            @endif
                            <a href="{{ $group['href'] }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $group['href'] }}</a>
                        @else
                            {{ $group['label'] }}
                        @endif
                    </div>
                </div>
                @if(!empty($group['hint']))
                    <div class="cabinet-sa-dup-group__hint">{{ $group['hint'] }}</div>
                @endif
                @php
                    $groupUrls = is_array($group['urls'] ?? null) ? $group['urls'] : [];
                    $groupUrlTotal = count($groupUrls);
                    $groupUrlPreview = 10;
                    // Не раздуваем DOM тысячами <a> в сквозном блоке.
                    $groupUrlExpandMax = 40;
                    $groupUrlsHead = array_slice($groupUrls, 0, $groupUrlPreview);
                    $groupUrlsTailAll = $groupUrlTotal > $groupUrlPreview
                        ? array_slice($groupUrls, $groupUrlPreview)
                        : [];
                    $groupUrlsTail = array_slice($groupUrlsTailAll, 0, $groupUrlExpandMax);
                    $groupUrlsHidden = max(0, count($groupUrlsTailAll) - count($groupUrlsTail));
                @endphp
                <ul class="cabinet-sa-dup-group__urls">
                    @foreach($groupUrlsHead as $u)
                        <li>
                            <a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a>
                        </li>
                    @endforeach
                </ul>
                @if($groupUrlsTail !== [] || $groupUrlsHidden > 0)
                    <details class="cabinet-sa-dup-group__more">
                        <summary class="cabinet-sa-dup-group__more-sum">
                            Ещё {{ number_format($groupUrlTotal - $groupUrlPreview, 0, '', ' ') }} URL
                        </summary>
                        @if($groupUrlsTail !== [])
                            <ul class="cabinet-sa-dup-group__urls cabinet-sa-dup-group__urls--more">
                                @foreach($groupUrlsTail as $u)
                                    <li>
                                        <a href="{{ $u['url'] }}" target="_blank" rel="noopener noreferrer">{{ $u['url'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if($groupUrlsHidden > 0)
                            <div class="cabinet-sa-dup-group__more-note text-muted small mt-2">
                                Показаны {{ number_format($groupUrlPreview + count($groupUrlsTail), 0, '', ' ') }}
                                из {{ number_format($groupUrlTotal, 0, '', ' ') }}.
                                Остальные {{ number_format($groupUrlsHidden, 0, '', ' ') }} —
                                в режиме
                                <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1]) }}">По страницам</a>.
                            </div>
                        @endif
                    </details>
                @endif
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
        $isPsiReport = in_array($code ?? '', ['psi_mobile', 'psi_desktop'], true);
    @endphp
    @if($isPsiReport)
        @include('pages.partials.site-audit-psi-report')
    @else
    @php
        $colspan = 2; // URL + Детали
        if (!empty($showReferrers)) { $colspan++; }
        if (!empty($canIgnore) || !empty($canNote)) { $colspan++; }
        $isRedirectReport = in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true);
        $isBrokenTarget = !empty($showReferrers) && ! $isRedirectReport;
        $isSerpTitleReport = ($code ?? '') === 'serp_title_mismatch';
        $showActions = !empty($canNote) || !empty($canIgnore);
        // Срочность задаётся на тип ошибки в конфиге. В обычном отчёте все строки одинаковые —
        // колонку «Приор.» показываем только в сводных (virtual), где реально смешаны уровни.
        $showSeverityCol = false;
        if (! empty($meta['virtual']) && ! empty($meta['codes']) && is_array($meta['codes'])) {
            $sevSet = [];
            foreach ($meta['codes'] as $childCode) {
                $sevSet[(string) config('site_audit.findings.' . $childCode . '.severity', 'info')] = true;
            }
            $showSeverityCol = count($sevSet) > 1;
        }
        if ($showSeverityCol) {
            $colspan++;
        }
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
    <div class="cabinet-sa-table-wrap{{ $isSerpTitleReport ? ' cabinet-sa-table-wrap--serp-title' : '' }}{{ $isBrokenTarget ? ' cabinet-sa-table-wrap--broken' : '' }}">
        <table class="table table-sm table-hover mb-0 cabinet-sa-findings-table">
            <colgroup>
                <col class="cabinet-sa-col-url">
                @if($showSeverityCol)
                    <col class="cabinet-sa-col-sev">
                @endif
                <col class="cabinet-sa-col-details">
                @if(!empty($showReferrers))
                    <col class="cabinet-sa-col-from">
                @endif
                @if($showActions)
                    <col class="cabinet-sa-col-actions">
                @endif
            </colgroup>
            <thead class="table-light">
            <tr>
                <th title="{{ $urlColTitle }}">
                    {{ $urlColLabel }}
                    @include('pages.partials.site-audit-tip', ['tip' => $urlTip])
                </th>
                @if($showSeverityCol)
                    <th title="В этом сводном отчёте смешаны разные типы ошибок — разная срочность.">
                        Приор.
                        @include('pages.partials.site-audit-tip', ['tip' => "Срочность разных типов в сводке.\nГрубые — чинить в первую очередь.\nИнфо — просто знать.\nВ обычном отчёте (один тип) колонка скрыта: все строки одного уровня."])
                    </th>
                @endif
                <th title="{{ $isSerpTitleReport ? 'Сравнение TITLE на сайте и в выдаче ПС' : 'Коротко: что именно не так (код ответа, дубль и т.п.).' }}">
                    {{ $isSerpTitleReport ? 'Сравнение title' : 'Детали' }}
                    @include('pages.partials.site-audit-tip', ['tip' => $isSerpTitleReport
                        ? "Слева — TITLE в HTML, справа — заголовок в сниппете ПС."
                        : "Кратко что не так: код ответа, какой дубль, какой запрос и т.д."])
                </th>
                @if(!empty($showReferrers))
                    <th title="Откуда URL попал в очередь проверки">
                        {{ $isBrokenTarget ? 'Страница со ссылкой' : 'Откуда' }}
                        @include('pages.partials.site-audit-tip', [
                            'tip' => $refColTip,
                        ])
                    </th>
                @endif
                @if($showActions)
                    <th class="cabinet-sa-th-actions" title="Заметка — комментарий. Исправлено — починили. Игнор — не ошибка. «?» на кнопке — подробнее.">
                        Действия
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
                        @php
                            $urlDisp = !empty($isBrokenTarget)
                                ? \App\Services\SiteAudit\SiteAuditFindingPresenter::brokenUrlDisplay((string) $row->url)
                                : ['display' => (string) $row->url, 'warn' => null];
                            $httpStatus = isset($rowMeta['status']) ? (int) $rowMeta['status'] : 0;
                        @endphp
                        <div class="cabinet-sa-url-block{{ !empty($urlDisp['warn']) ? ' cabinet-sa-url-block--warn' : '' }}">
                            @if(!empty($isBrokenTarget) && $httpStatus >= 400)
                                <span class="cabinet-sa-status-pill {{ $httpStatus >= 500 ? 'cabinet-sa-status-pill--5xx' : 'cabinet-sa-status-pill--4xx' }}">{{ $httpStatus }}</span>
                            @endif
                            <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $urlDisp['display'] }}</a>
                            @if(!empty($urlDisp['warn']))
                                <div class="cabinet-sa-url-block__warn" title="{{ $row->url }}">{{ $urlDisp['warn'] }}</div>
                            @endif
                            @if($isIgn)
                                <span class="badge text-bg-light border ms-1">игнор</span>
                            @endif
                            @if($isFixed)
                                <span class="badge text-bg-success ms-1">исправлено</span>
                            @endif
                        </div>
                    </td>
                    @if(!empty($showSeverityCol))
                        <td class="cabinet-sa-sev-cell">
                            @php $sevKey = (string) ($row->severity ?? 'info'); @endphp
                            <span class="cabinet-sa-sev-badge cabinet-sa-sev-badge--{{ $sevKey }}" title="{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($sevKey) }}">
                                {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sevKey) }}
                            </span>
                        </td>
                    @endif
                    <td class="small cabinet-sa-details-cell">
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
                        <td class="small cabinet-sa-from-cell">
                            @php
                                $originLabel = trim((string) ($rowMeta['origin_label'] ?? ''));
                                $discoveredVia = trim((string) ($rowMeta['discovered_via'] ?? ''));
                                $discoveredFrom = trim((string) ($rowMeta['discovered_from'] ?? ''));
                                if ($discoveredVia === '' && $discoveredFrom === '' && $referrerCount > 0) {
                                    $discoveredVia = 'link';
                                    $discoveredFrom = trim((string) ($referrers[0] ?? ''));
                                }
                                if ($discoveredFrom === '' && !empty($rowMeta['from'])) {
                                    $discoveredVia = $discoveredVia !== '' ? $discoveredVia : 'link';
                                    $discoveredFrom = trim((string) $rowMeta['from']);
                                }
                            @endphp
                            @if($discoveredVia === 'link' && $discoveredFrom !== '')
                                <a class="cabinet-sa-url-break" href="{{ $discoveredFrom }}" target="_blank" rel="noopener noreferrer">{{ $discoveredFrom }}</a>
                            @elseif(in_array($discoveredVia, ['sitemap', 'seed', 'home'], true) && $originLabel !== '')
                                @php
                                    $sitemapHref = trim((string) ($rowMeta['sitemap_href'] ?? ''));
                                    $isSitemapOrigin = $discoveredVia === 'sitemap'
                                        || ! empty($rowMeta['from_sitemap'])
                                        || stripos($originLabel, 'sitemap') !== false;
                                @endphp
                                @if($isSitemapOrigin && $sitemapHref !== '')
                                    <a class="cabinet-sa-url-break" href="{{ $sitemapHref }}" target="_blank" rel="noopener noreferrer">{{ $originLabel }}</a>
                                @elseif($discoveredVia === 'home' && !empty($project->domain))
                                    <a class="cabinet-sa-url-break" href="https://{{ $project->domain }}/" target="_blank" rel="noopener noreferrer">{{ $originLabel }}</a>
                                @else
                                    {{ $originLabel }}
                                @endif
                            @elseif($originLabel !== '')
                                {{ $originLabel }}
                            @elseif($referrerCount === 0)
                                <span class="text-muted">—</span>
                            @endif
                            @if($referrerCount > 1)
                                <ul class="list-unstyled mb-0 cabinet-sa-referrers mt-1">
                                    @foreach(array_slice($referrers, 0, 5) as $ref)
                                        @if($discoveredVia === 'link' && $ref === $discoveredFrom)
                                            @continue
                                        @endif
                                        <li><a class="cabinet-sa-url-break" href="{{ $ref }}" target="_blank" rel="noopener noreferrer">{{ $ref }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    @endif
                    @if($showActions)
                        <td class="cabinet-sa-actions-cell">
                            @if(!empty($canNote) && $isFixed)
                                <span class="cabinet-sa-note-flag">исправлено</span>
                            @endif
                            @if(!empty($canNote) && $noteComment !== '')
                                <div class="cabinet-sa-note-text">{{ $noteComment }}</div>
                            @endif
                            <div class="cabinet-sa-actions">
                                @if(!empty($canNote) && !empty($row->id))
                                    <div class="cabinet-sa-act cabinet-sa-act--note">
                                        <label class="cabinet-sa-act__main" for="sa-note-{{ (int) $row->id }}">Заметка</label>
                                        @include('pages.partials.site-audit-action-help', [
                                            'tip' => "Комментарий к ошибке.\nСохраняется навсегда (тип + URL).\nОткройте → текст → «Сохранить».",
                                        ])
                                    </div>
                                    @if(!$isFixed)
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--fixed">
                                                <button type="submit" name="status" value="fixed" class="cabinet-sa-act__main">Исправлено</button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Починили — спрятать из счётчиков.\nВернуть: «Открыть» или «Показать исправленные».\nНе путать с «Игнор».",
                                                ])
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <input type="hidden" name="comment" value="{{ $noteComment }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--open">
                                                <button type="submit" name="status" value="open" class="cabinet-sa-act__main">Открыть</button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Снова учитывать в счётчиках (снять «Исправлено»).",
                                                ])
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canIgnore) && !empty($row->id))
                                    @if($isIgn)
                                        <form method="POST" action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--restore">
                                                <button type="submit" class="cabinet-sa-act__main">Вернуть</button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Снова считать ошибкой (снять игнор).",
                                                ])
                                            </div>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('pages.site-audit.ignore', $crawl->id) }}" class="cabinet-sa-act-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <div class="cabinet-sa-act cabinet-sa-act--ignore">
                                                <button type="submit" class="cabinet-sa-act__main">Игнор</button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Не ошибка / ложное срабатывание.\nУбираем из отчёта навсегда, пока не нажмёте «Вернуть».\nИгнор ≠ Исправлено.",
                                                ])
                                            </div>
                                        </form>
                                    @endif
                                @endif
                                @if(!empty($canNote) && !empty($row->id))
                                    <input type="checkbox" class="cabinet-sa-note-toggle" id="sa-note-{{ (int) $row->id }}">
                                    <div class="cabinet-sa-note-panel">
                                        <form method="POST" action="{{ route('pages.site-audit.note', $crawl->id) }}" class="cabinet-sa-note-form">
                                            @csrf
                                            <input type="hidden" name="finding_id" value="{{ $row->id }}">
                                            <textarea name="comment" rows="2" class="form-control form-control-sm"
                                                      placeholder="Текст заметки…">{{ $noteComment }}</textarea>
                                            <div class="cabinet-sa-act cabinet-sa-act--save">
                                                <button type="submit" name="status" value="{{ $isFixed ? 'fixed' : 'open' }}"
                                                        class="cabinet-sa-act__main">Сохранить</button>
                                                @include('pages.partials.site-audit-action-help', [
                                                    'tip' => "Только текст заметки.\n«Исправлено» и «Игнор» — кнопки выше.",
                                                ])
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </td>
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
    @endif {{-- /isPsiReport --}}
@endif

@include('pages.partials.site-audit-pager', [
    'page' => $page,
    'pages' => $pages,
    'total' => $total ?? null,
])
@endif