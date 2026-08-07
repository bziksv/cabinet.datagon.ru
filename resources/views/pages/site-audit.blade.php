@component('component.card', [
    'title' => 'Аудит сайта',
    'titleHtml' => e('Аудит сайта') . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-audit'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sa-page">
        @if(session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-warning py-2">{{ session('error') }}</div>
        @endif

        @include('pages.partials.site-audit-beta-banner')

        <div class="cabinet-sa-lead px-4 py-3 mb-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-sa-lead__icon" aria-hidden="true"><i class="bi bi-clipboard2-pulse"></i></span>
                <div class="flex-grow-1">
                    <p class="mb-1 fw-semibold text-body">Технический аудит сайта</p>
                    <p class="mb-2 small text-secondary">
                        Обходим сайт по sitemap и ссылкам, смотрим robots, собираем ошибки в отчёт.
                        Можно кинуть список URL — тогда только их, без дальнейшего обхода.
                        Несколько доменов — отдельные проекты.
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-primary cabinet-sa-tour-start" id="sa-tour-start">
                        <i class="bi bi-lightbulb me-1" aria-hidden="true"></i>Как пользоваться…
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <section class="card border shadow-sm cabinet-sa-panel h-100" data-sa-tour="new-crawl">
                    <div class="card-body">
                        <h2 class="cabinet-sa-step-title h6 mb-3">
                            <span class="cabinet-sa-step-badge">1</span>
                            Новый краул
                        </h2>

                        <div class="mb-3 cabinet-sa-field" data-sa-tour="domains">
                            <label class="form-label fw-medium" for="sa-domain">
                                Домены
                                @include('pages.partials.site-audit-tip', ['tip' => "Один или несколько сайтов — каждый домен с новой строки.\nМожно без https://: titlo.ru\nИли целиком URL: https://titlo.ru/ — возьмём только хост.\nДля каждого домена создаётся свой проект и краул (лимит — по тарифу). Доп. URL и исключения применяются ко всем."])
                            </label>
                            <textarea class="form-control" id="sa-domain" rows="3" placeholder="example.com&#10;shop.example.com&#10;https://another.ru/" autocomplete="off"></textarea>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-extra-hosts">
                                Доп. хосты в одном project <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "Только если выше указан один основной домен.\nПоддомены (shop.example.com, blog.example.com) войдут в тот же краул как внутренние.\nНесколько доменов в поле «Домены» — по-прежнему отдельные проекты."])
                            </label>
                            <textarea class="form-control" id="sa-extra-hosts" rows="2" placeholder="shop.example.com&#10;blog.example.com" autocomplete="off"></textarea>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-seeds">
                                Страницы / доп. URL <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "По одному URL на строку, лучше с https://.\nБез галочки ниже — это доп. семена: сайт обходится как обычно (sitemap + ссылки), эти URL точно попадут в очередь.\nС галочкой «только эти страницы» — сканируются исключительно перечисленные URL: без sitemap, без главной «насильно» и без дообхода по ссылкам.\nURL с разных доменов автоматически разбиваются на отдельные проекты/краулы.\nМожно не заполнять «Домены», если галочка включена — домен возьмём из URL."])
                            </label>
                            <textarea class="form-control" id="sa-seeds" rows="3" placeholder="https://example.com/page&#10;https://other.ru/about"></textarea>
                            <div class="form-check mt-2 mb-0">
                                <input type="checkbox" class="form-check-input" id="sa-pages-only">
                                <label class="form-check-label" for="sa-pages-only">
                                    Сканировать только эти страницы
                                    <span class="text-secondary">(без sitemap и дообхода; разные сайты → разные проекты)</span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-robots">
                                Виртуальный robots.txt <span class="text-secondary fw-normal">(опционально)</span>
                                @include('pages.partials.site-audit-tip', ['tip' => "По умолчанию краул читает живой /robots.txt сайта и не ходит по Disallow (корень оставляем для диагностики).\nЕсли вставить сюда свой robots.txt — он подменит файл на сайте: теми же правилами режем обход и пишем findings.\nУдобно закрыть /cart, /admin, utm без отдельного списка исключений.\nПример:\nUser-agent: *\nDisallow: /cart\nDisallow: /admin\nAllow: /"])
                            </label>
                            <textarea class="form-control font-monospace" id="sa-robots" rows="5"
                                      placeholder="User-agent: *&#10;Disallow: /cart&#10;Disallow: /admin&#10;Allow: /"></textarea>
                        </div>

                        <div data-sa-tour="speed">
                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-speed">
                                Скорость на поток
                                @include('pages.partials.site-audit-tip', ['tip' => "Лимит стартов запросов в секунду на один поток.\nИтоговая нагрузка ≈ потоки × скорость на поток (но сайт/CDN могут отвечать медленнее).\nМедленнее — мягче к хостингу и антиботу.\nТурбо и высокая скорость на чужих сайтах часто дают 403/429 или временный бан — начинайте с обычной/медленной."])
                            </label>
                            <select class="form-select" id="sa-speed">
                                <option value="slow">Медленно (~1 URL/с на поток)</option>
                                <option value="normal" selected>Обычная (~5 URL/с на поток)</option>
                                <option value="fast">Быстрая (~10 URL/с на поток)</option>
                                <option value="turbo">Турбо (~15 URL/с на поток) — только свои сайты</option>
                            </select>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-concurrency">
                                Потоки (параллельные запросы)
                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько HTTP-запросов к сайту одновременно.\nЛимит тарифа: Free 1 / Optimal 2 / Ultimate 4 / Maximum 8.\nНе лупите сразу максимум потоков: хостинги и WAF ограничивают параллельность — получите 429/бан и пустые findings.\nНа тяжёлых своих сайтах можно поднять потоки осторожно, смотря на ответы сервера."])
                            </label>
                            <select class="form-select" id="sa-concurrency">
                                @php
                                    $maxConc = max(1, (int) ($concurrencyLimit ?? config('site_audit.max_concurrency', 8)));
                                @endphp
                                @for($n = 1; $n <= $maxConc; $n++)
                                    <option value="{{ $n }}" @if($n === 1) selected @endif>
                                        {{ $n }} {{ $n === 1 ? 'поток' : ($n < 5 ? 'потока' : 'потоков') }}
                                    </option>
                                @endfor
                            </select>
                            <div class="form-text">По тарифу доступно до {{ $maxConc }}</div>
                        </div>

                        <div class="mb-3 cabinet-sa-field">
                            <label class="form-label fw-medium" for="sa-limit">
                                Лимит URL
                                @include('pages.partials.site-audit-tip', ['tip' => "Сколько страниц сканировать в этом крауле.\nНе выше лимита тарифа (сейчас {{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}).\nМожно поставить меньше, чтобы быстрее прогнать важные разделы."])
                            </label>
                            <input type="text" class="form-control sa-num-space" id="sa-limit"
                                   inputmode="numeric" autocomplete="off"
                                   value="{{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}"
                                   data-min="1"
                                   data-max="{{ (int) ($pagesLimit ?? 100) }}">
                            <div class="form-text">
                                Макс. по тарифу: {{ number_format((int) ($pagesLimit ?? 100), 0, '', ' ') }}
                                · проектов {{ (int) ($projectsUsed ?? 0) }}/{{ (int) ($projectsLimit ?? 1) }}
                            </div>
                        </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-primary" id="sa-start">
                                <i class="bi bi-play-fill me-1"></i>Запустить
                            </button>
                            <div id="sa-msg" class="small text-secondary"></div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="card border shadow-sm cabinet-sa-panel h-100" data-sa-tour="projects">
                    <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h2 class="h6 mb-0 fw-semibold">Проекты</h2>
                        <div class="small text-secondary">
                            @if(isset($projectsLimit))
                                проектов {{ $projects->count() }} / {{ (int) $projectsLimit }}
                            @endif
                            @if(isset($schedulesLimit))
                                · автоснятий {{ (int) ($schedulesUsed ?? 0) }} / {{ (int) $schedulesLimit }}
                            @endif
                        </div>
                    </div>
                    @forelse($projects as $project)
                        @php
                            $last = $project->crawls->first();
                            $sch = ($schedules ?? collect())->get($project->id);
                            $schSettings = ($sch && is_array($sch->settings_json)) ? $sch->settings_json : [];
                            $schWeekday = (int) ($schSettings['weekday'] ?? now()->dayOfWeekIso);
                            $schHour = (int) ($schSettings['hour'] ?? 4);
                            $schSpeed = (string) ($schSettings['crawl_speed'] ?? 'normal');
                            $schConc = max(1, (int) ($schSettings['concurrency'] ?? 1));
                            $schPages = (int) ($schSettings['pages_limit'] ?? ($pagesLimit ?? 100));
                            $maxConc = max(1, (int) ($concurrencyLimit ?? 1));
                            $maxPages = max(1, (int) ($pagesLimit ?? 100));
                        @endphp
                        <div class="cabinet-sa-project">
                            <div class="cabinet-sa-project__main">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-body text-truncate">{{ $project->domain }}</div>
                                    <div class="small text-secondary">
                                        @if($last)
                                            последний краул
                                            <a href="{{ route('pages.site-audit.crawl.show', $last->id) }}">#{{ $last->id }}</a>
                                            · {{ $last->statusLabelRu() }}
                                            · {{ $last->pages_fetched }}/{{ $last->pages_total }}
                                        @else
                                            ещё не запускался
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-shrink-0 gap-1">
                                        @if($last)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $last->id) }}">Открыть</a>
                                            @if(($project->crawls_count ?? 0) > 1)
                                                <a class="btn btn-sm btn-outline-secondary"
                                                   href="{{ route('pages.site-audit.crawl.show', $last->id) }}#sa-archive">Архив</a>
                                            @endif
                                        @endif
                                    <form method="POST" action="{{ route('pages.site-audit.project.destroy', $project->id) }}" class="d-inline"
                                          data-cabinet-confirm="Удалить проект {{ e($project->domain) }} и все краулы?"
                                          data-cabinet-confirm-title="Удаление проекта"
                                          data-cabinet-confirm-ok="Удалить"
                                          data-cabinet-confirm-danger="1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                    </form>
                                </div>
                            </div>
                            @if(!empty($canSchedule))
                                <form method="POST" action="{{ route('pages.site-audit.schedule', $project->id) }}" class="cabinet-sa-project__schedule" data-sa-tour="schedule">
                                    @csrf
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="cabinet-sa-check-row mb-0">
                                            <div class="form-check mb-0">
                                                <input type="checkbox" class="form-check-input" id="sa-sch-{{ $project->id }}"
                                                       name="enabled" value="1" {{ $sch && $sch->enabled ? 'checked' : '' }}>
                                                <label class="form-check-label fw-medium" for="sa-sch-{{ $project->id }}">Авторасписание</label>
                                            </div>
                                            @include('pages.partials.site-audit-tip', ['tip' => "Автозапуск аудита в выбранный день и час (МСК).\nЛимит слотов: Free 0 / Optimal 2 / Ultimate 5 / Maximum 10.\nЧасы 11–14 недоступны (пик).\nСписывает краул из месячного лимита."])
                                        </div>
                                        @if($sch && $sch->enabled && $sch->next_run_at)
                                            <span class="badge text-bg-primary">
                                                след. {{ $sch->next_run_at->format('d.m.Y H:i') }} МСК
                                            </span>
                                        @else
                                            <span class="small text-secondary">выкл. — настройки скрыты</span>
                                        @endif
                                    </div>
                                    <div class="cabinet-sa-project__schedule-body">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-freq-{{ $project->id }}">Как часто</label>
                                            <select name="frequency" id="sa-sch-freq-{{ $project->id }}" class="form-select form-select-sm">
                                                @foreach(($scheduleFrequencies ?? []) as $freqCode => $freqLabel)
                                                    <option value="{{ $freqCode }}"
                                                        {{ ($sch ? \App\SiteAuditSchedule::normalizeFrequency($sch->frequency) : 'weekly') === $freqCode ? 'selected' : '' }}>
                                                        {{ $freqLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-wd-{{ $project->id }}">День недели</label>
                                            <select name="weekday" id="sa-sch-wd-{{ $project->id }}" class="form-select form-select-sm">
                                                @foreach(($scheduleWeekdays ?? []) as $wd => $wdLabel)
                                                    <option value="{{ $wd }}" {{ (int) $schWeekday === (int) $wd ? 'selected' : '' }}>{{ $wdLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-hour-{{ $project->id }}">Час (МСК)</label>
                                            <select name="hour" id="sa-sch-hour-{{ $project->id }}" class="form-select form-select-sm">
                                                @php
                                                    $peakHours = [11, 12, 13, 14];
                                                    if (in_array($schHour, $peakHours, true)) {
                                                        $schHour = 4;
                                                    }
                                                @endphp
                                                @for($h = 0; $h <= 23; $h++)
                                                    @if(in_array($h, $peakHours, true))
                                                        <option value="{{ $h }}" disabled>{{ sprintf('%02d:00', $h) }} — пик</option>
                                                    @else
                                                        <option value="{{ $h }}" {{ $schHour === $h ? 'selected' : '' }}>
                                                            {{ sprintf('%02d:00', $h) }}
                                                        </option>
                                                    @endif
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label small mb-1" for="sa-sch-speed-{{ $project->id }}">Скорость на поток</label>
                                            <select name="crawl_speed" id="sa-sch-speed-{{ $project->id }}" class="form-select form-select-sm">
                                                <option value="slow" {{ $schSpeed === 'slow' ? 'selected' : '' }}>Медленно (~1 URL/с на поток)</option>
                                                <option value="normal" {{ $schSpeed === 'normal' ? 'selected' : '' }}>Обычная (~5 URL/с на поток)</option>
                                                <option value="fast" {{ $schSpeed === 'fast' ? 'selected' : '' }}>Быстрая (~10 URL/с на поток)</option>
                                                <option value="turbo" {{ $schSpeed === 'turbo' ? 'selected' : '' }}>Турбо (~15 URL/с на поток) — только свои сайты</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1" for="sa-sch-conc-{{ $project->id }}">Потоки</label>
                                            <select name="concurrency" id="sa-sch-conc-{{ $project->id }}" class="form-select form-select-sm">
                                                @for($n = 1; $n <= $maxConc; $n++)
                                                    <option value="{{ $n }}" {{ $schConc === $n ? 'selected' : '' }}>{{ $n }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="form-label small mb-1" for="sa-sch-limit-{{ $project->id }}">Лимит URL</label>
                                            <input type="text" class="form-control form-control-sm sa-num-space" name="pages_limit"
                                                   id="sa-sch-limit-{{ $project->id }}"
                                                   inputmode="numeric" autocomplete="off"
                                                   value="{{ number_format((int) min($schPages, $maxPages), 0, '', ' ') }}"
                                                   data-min="1" data-max="{{ $maxPages }}">
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">Сохранить</button>
                                        </div>
                                    </div>
                                    <div class="small cabinet-sa-project__schedule-note mt-2">
                                        Запуск в выбранный день и час (МСК). 11:00–14:00 недоступны (пик). После сохранения — точная дата «след. …».
                                    </div>
                                    </div>
                                </form>
                            @else
                                <div class="cabinet-sa-project__schedule small text-secondary">
                                    Авторасписание недоступно на бесплатном тарифе (0 слотов). Optimal — 2, Ultimate — 5, Maximum — 10.
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="card-body">
                            <div class="alert alert-light border text-center py-4 mb-0 text-secondary">
                                Проектов пока нет — запустите первый краул.
                            </div>
                        </div>
                    @endforelse
                </section>
            </div>
        </div>

        <section class="card border shadow-sm cabinet-sa-panel mt-3" id="sa-history" data-sa-tour="history">
            <div class="card-header py-2 px-3">
                <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
                    <h2 class="h6 mb-0 fw-semibold">История краулов</h2>
                    <form method="GET" action="{{ route('pages.site-audit') }}#sa-history" class="d-flex align-items-center gap-2 ms-auto" id="sa-history-search">
                        <label class="visually-hidden" for="sa-history-domain">Поиск по домену</label>
                        <input type="search" class="form-control form-control-sm" id="sa-history-domain" name="domain"
                               value="{{ $historyDomain ?? '' }}"
                               placeholder="Поиск по домену…"
                               style="min-width:11rem;max-width:16rem"
                               autocomplete="off">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Найти</button>
                        @if(!empty($historyDomain))
                            <a href="{{ route('pages.site-audit') }}#sa-history" class="btn btn-sm btn-link text-secondary px-1">Сбросить</a>
                        @endif
                    </form>
                </div>
                @if(!empty($historyDomain))
                    <div class="small text-secondary mt-1">
                        Найдено: {{ method_exists($crawls, 'total') ? $crawls->total() : $crawls->count() }} по «{{ $historyDomain }}»
                    </div>
                @endif
                <div class="small text-secondary mt-1 mb-0">
                    После окончания платного тарифа история аудита хранится ещё 14 дней, затем удаляется автоматически.
                </div>
            </div>
            @if(!empty($historyPurgeNotice['show']))
                <div class="alert alert-warning border-0 rounded-0 mb-0 px-3 py-2 small">
                    Вы на бесплатном тарифе после платного.
                    История аудита будет удалена
                    @if(($historyPurgeNotice['days_left'] ?? 0) > 0)
                        через {{ $historyPurgeNotice['days_left'] }} дн. ({{ $historyPurgeNotice['purge_at'] ?? '' }}).
                    @else
                        в ближайшее время (срок {{ $historyPurgeNotice['purge_at'] ?? '' }}).
                    @endif
                    Продлите тариф, чтобы сохранить данные.
                </div>
            @endif
            <div class="card-body p-0">
                <div class="table-responsive cabinet-sa-table-wrap cabinet-sa-table-wrap--flush">
                    <table class="table table-sm table-hover align-middle mb-0" id="sa-history-table">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Домен</th>
                            <th>Статус</th>
                            <th style="min-width:8rem">Прогресс</th>
                            <th class="text-nowrap">Начало</th>
                            <th class="text-nowrap">Конец</th>
                            <th>Настройки</th>
                            <th>Размер</th>
                            <th>Грубые</th>
                            <th>Прочие</th>
                            <th>Пред.</th>
                            <th>Инфо</th>
                            <th class="text-end"></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($crawls as $c)
                            @php
                                $b = $c->buckets_json ?: [];
                                $stClass = $c->statusCssClass();
                                $sizeBytes = (int) (($crawlSizes ?? [])[$c->id] ?? 0);
                                $pct = $c->pages_total > 0
                                    ? (int) round(100 * $c->pages_fetched / max(1, $c->pages_total))
                                    : 0;
                                $finished = $c->isFinished();
                                $rawSettings = $c->settings_json_raw ?? null;
                                if (is_string($rawSettings)) {
                                    $s = json_decode($rawSettings, true) ?: [];
                                } elseif (is_array($rawSettings)) {
                                    $s = $rawSettings;
                                } else {
                                    $s = [];
                                }
                                $concurrency = max(1, (int) ($s['concurrency'] ?? 1));
                                $speed = (string) ($s['crawl_speed'] ?? '—');
                                $rps = isset($s['rps']) ? (float) $s['rps'] : null;
                                $pagesOnly = ! empty($s['pages_only']);
                                $limitShow = (int) ($c->pages_limit ?: ($s['pages_limit'] ?? 0));
                            @endphp
                            <tr data-crawl-id="{{ $c->id }}"
                                data-finished="{{ $finished ? '1' : '0' }}"
                                data-status-url="{{ route('pages.site-audit.crawl.status', $c->id) }}"
                                class="{{ $finished ? '' : 'cabinet-sa-row--active' }}">
                                <td class="text-secondary">#{{ $c->id }}</td>
                                <td class="fw-medium">
                                    {{ optional($c->project)->domain ?? '—' }}
                                    @if($pagesOnly)
                                        <span class="badge text-bg-light border ms-1" title="Только указанные страницы">страницы</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="cabinet-sa-status cabinet-sa-status--{{ $stClass }}" data-sa-status>
                                        {{ $c->statusLabelRu() }}
                                    </span>
                                </td>
                                <td class="cabinet-sa-progress-cell" data-sa-progress>
                                    @php
                                        $fetchedN = (int) $c->pages_fetched;
                                        $totalN = max(0, (int) $c->pages_total);
                                        $isFailed = $c->status === 'failed' || $c->status === 'cancelled';
                                        $indeterminate = ! $finished && ($totalN < 1 || in_array($c->status, ['queued', 'queued_wait', 'discovering'], true));
                                        // /html/UI/general.html — Progress
                                        if ($finished && ! $isFailed) {
                                            $barClass = 'progress-bar bg-success';
                                            $fillPct = 100;
                                            $labelText = $fetchedN . '/' . $totalN;
                                        } elseif ($isFailed) {
                                            $barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-danger';
                                            $fillPct = $totalN > 0 ? (int) round(100 * $fetchedN / max(1, $totalN)) : 0;
                                            if ($fillPct < 1) {
                                                $fillPct = 100;
                                            }
                                            $labelText = $fetchedN . '/' . $totalN;
                                        } elseif ($indeterminate) {
                                            $barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                                            $fillPct = 100;
                                            $labelText = $totalN > 0 ? ($fetchedN . '/' . $totalN) : '…';
                                        } else {
                                            $barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                                            $fillPct = max(0, (int) $pct);
                                            $labelText = $fetchedN . '/' . $totalN;
                                        }
                                        $hint = $c->status === 'queued_wait'
                                            ? 'ждёт свободный слот на сервере'
                                            : ($c->status === 'queued'
                                                ? 'запуск'
                                                : ($c->status === 'discovering' ? 'сбор URL' : ($c->status === 'aggregating' ? 'агрегация' : ($isFailed ? 'ошибка' : ($finished ? 'готово' : 'сканирование')))));
                                    @endphp
                                    <div class="progress"
                                         role="progressbar"
                                         aria-label="{{ $hint }}"
                                         aria-valuenow="{{ $fillPct }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         title="{{ $hint }} · {{ $fetchedN }}/{{ $totalN }}">
                                        <div class="{{ $barClass }}" style="width: {{ $fillPct }}%; border-radius: 0.375rem">{{ $labelText }}</div>
                                    </div>
                                </td>
                                <td class="text-nowrap small text-secondary" data-sa-started>
                                    {{ $c->started_at ? $c->started_at->format('d.m.Y H:i') : ($c->created_at ? $c->created_at->format('d.m.Y H:i') : '—') }}
                                </td>
                                <td class="text-nowrap small text-secondary" data-sa-finished>
                                    @if($c->finished_at)
                                        {{ $c->finished_at->format('d.m.Y H:i') }}
                                    @elseif($finished)
                                        —
                                    @else
                                        @php $eta = $c->estimateFinishedAtFormatted(); @endphp
                                        @if($eta)
                                            <span class="text-muted" title="Оценка по текущей скорости">~{{ $eta }}</span>
                                        @else
                                            <span class="text-muted" title="Слишком рано для оценки">~…</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="small" data-sa-settings>
                                    <div class="text-nowrap">
                                        {{ $concurrency }} {{ $concurrency === 1 ? 'поток' : ($concurrency < 5 ? 'потока' : 'потоков') }}
                                        · {{ $speed }}@if($rps !== null) ({{ rtrim(rtrim(number_format($rps, 1, '.', ''), '0'), '.') }}/с)@endif
                                    </div>
                                    <div class="text-secondary text-nowrap">
                                        @if($limitShow > 0)
                                            лимит {{ number_format($limitShow, 0, '', ' ') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-nowrap" data-sa-size>
                                    @php
                                        $sizeClass = 'cabinet-sa-size--sm';
                                        if ($sizeBytes >= 80 * 1024) {
                                            $sizeClass = 'cabinet-sa-size--lg';
                                        } elseif ($sizeBytes >= 30 * 1024) {
                                            $sizeClass = 'cabinet-sa-size--md';
                                        }
                                    @endphp
                                    <span class="cabinet-sa-size {{ $sizeClass }}" title="payload в БД (pages + findings + meta), без HTML">
                                        ~{{ \App\Services\SiteAudit\SiteAuditCrawlStorage::formatBytes($sizeBytes) }}
                                    </span>
                                </td>
                                <td data-sa-bucket="critical">{{ $b['critical'] ?? '—' }}</td>
                                <td data-sa-bucket="other">{{ $b['other'] ?? '—' }}</td>
                                <td data-sa-bucket="warning">{{ $b['warning'] ?? '—' }}</td>
                                <td data-sa-bucket="info">{{ $b['info'] ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <span class="cabinet-sa-row-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.site-audit.crawl.show', $c->id) }}">Сводка</a>
                                        @if(! $finished)
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.cancel', $c->id) }}" class="d-inline"
                                                  data-sa-cancel-crawl
                                                  data-cabinet-confirm="Остановить краул #{{ $c->id }}? Уже скачанные страницы останутся."
                                                  data-cabinet-confirm-title="Остановка краула"
                                                  data-cabinet-confirm-ok="Остановить"
                                                  data-cabinet-confirm-danger="1">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Стоп</button>
                                            </form>
                                        @endif
                                        @if($finished)
                                            @php
                                                $canResume = (new \App\Services\SiteAudit\SiteAuditCrawlEngine())->canResume($c);
                                            @endphp
                                            @if($canResume)
                                                <form method="POST" action="{{ route('pages.site-audit.crawl.continue', $c->id) }}" class="d-inline"
                                                      data-cabinet-confirm="Продолжить краул #{{ $c->id }} с {{ number_format((int) $c->pages_fetched, 0, '', ' ') }} URL? Уже скачанные страницы сохранятся."
                                                      data-cabinet-confirm-title="Продолжить краул"
                                                      data-cabinet-confirm-ok="Продолжить">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Продолжить</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.repeat', $c->id) }}" class="d-inline"
                                                  data-cabinet-confirm="Повторить краул для {{ e(optional($c->project)->domain ?? 'проекта') }} с теми же настройками? Начнётся новый краул с нуля."
                                                  data-cabinet-confirm-title="Новый краул"
                                                  data-cabinet-confirm-ok="Повторить">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>
                                            </form>
                                            <form method="POST" action="{{ route('pages.site-audit.crawl.destroy', $c->id) }}" class="d-inline"
                                                  data-cabinet-confirm="Удалить краул #{{ $c->id }}?"
                                                  data-cabinet-confirm-title="Удаление краула"
                                                  data-cabinet-confirm-ok="Удалить"
                                                  data-cabinet-confirm-danger="1">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                                            </form>
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr data-sa-empty><td colspan="13" class="text-secondary px-3 py-4 text-center">История пуста</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($crawls, 'hasPages') && $crawls->hasPages())
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-top">
                        <div class="small text-secondary">
                            {{ $crawls->firstItem() }}–{{ $crawls->lastItem() }}
                            из {{ number_format($crawls->total(), 0, '', ' ') }}
                        </div>
                        <nav title="Страницы истории краулов">
                            {{ $crawls->links('pagination::bootstrap-4') }}
                        </nav>
                    </div>
                @elseif(method_exists($crawls, 'total') && $crawls->total() > 0)
                    <div class="small text-secondary px-3 py-2 border-top">
                        Всего {{ number_format($crawls->total(), 0, '', ' ') }}
                    </div>
                @endif
            </div>
        </section>
    </div>

    @slot('js')
        @include('partials.cabinet-confirm-modal')
        <script>
            (function () {
                var startBtn = document.getElementById('sa-start');
                var msg = document.getElementById('sa-msg');
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
                var historyTable = document.getElementById('sa-history-table');
                var pollTimers = {};

                function saParseIntSpaces(v) {
                    var s = String(v == null ? '' : v).replace(/[\s\u00a0\u202f]/g, '');
                    var n = parseInt(s, 10);
                    return isNaN(n) ? 0 : n;
                }

                function saFormatIntSpaces(n) {
                    n = Math.max(0, Math.floor(Number(n) || 0));
                    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                }

                function saBindNumSpace(inp) {
                    if (!inp || inp._saNumBound) return;
                    inp._saNumBound = true;
                    inp.addEventListener('focus', function () {
                        inp.value = String(saParseIntSpaces(inp.value) || '');
                    });
                    inp.addEventListener('blur', function () {
                        var min = saParseIntSpaces(inp.getAttribute('data-min') || '1') || 1;
                        var maxAttr = inp.getAttribute('data-max');
                        var max = maxAttr != null && maxAttr !== '' ? saParseIntSpaces(maxAttr) : 0;
                        var n = saParseIntSpaces(inp.value);
                        if (n < min) n = min;
                        if (max > 0 && n > max) n = max;
                        inp.value = saFormatIntSpaces(n);
                    });
                }

                document.querySelectorAll('.sa-num-space').forEach(saBindNumSpace);

                document.querySelectorAll('form.cabinet-sa-project__schedule').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        var lim = form.querySelector('.sa-num-space[name="pages_limit"]');
                        if (lim) lim.value = String(saParseIntSpaces(lim.value) || 1);
                    });
                });

                function scrollToHistory() {
                    var el = document.getElementById('sa-history');
                    if (el && el.scrollIntoView) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }

                function updateRow(row, j) {
                    if (!row || !j) return;
                    var statusEl = row.querySelector('[data-sa-status]');
                    if (statusEl) {
                        statusEl.textContent = j.status_label || j.status;
                        statusEl.className = 'cabinet-sa-status cabinet-sa-status--' +
                            (j.status === 'done' ? 'done' : ((j.status === 'failed' || j.status === 'cancelled') ? 'failed' : 'run'));
                    }
                    var prog = row.querySelector('[data-sa-progress]');
                    if (prog) {
                        var fetched = j.pages_fetched || 0;
                        var total = j.pages_total || 0;
                        var pct = j.progress_pct || (total > 0 ? Math.round(100 * fetched / total) : 0);
                        var st = j.status || '';
                        var isFailed = st === 'failed' || st === 'cancelled';
                        var finished = !!j.finished;
                        var indeterminate = !finished && (total < 1 || st === 'queued' || st === 'queued_wait' || st === 'discovering');
                        var barClass, fill, label, hint;
                        // /html/UI/general.html — Progress
                        if (finished && !isFailed) {
                            barClass = 'progress-bar bg-success';
                            fill = 100;
                            label = fetched + '/' + total;
                            hint = 'готово';
                        } else if (isFailed) {
                            barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-danger';
                            fill = total > 0 ? Math.round(100 * fetched / Math.max(1, total)) : 0;
                            if (fill < 1) fill = 100;
                            label = fetched + '/' + total;
                            hint = st === 'cancelled' ? 'остановлен' : 'ошибка';
                        } else if (indeterminate) {
                            barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';
                            fill = 100;
                            label = total > 0 ? (fetched + '/' + total) : '…';
                            hint = (st === 'queued_wait')
                                ? 'ждёт свободный слот на сервере'
                                : ((st === 'queued') ? 'запуск' : (st === 'discovering' ? 'сбор URL' : 'ожидание'));
                        } else {
                            barClass = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
                            fill = pct;
                            label = fetched + '/' + total;
                            hint = st === 'aggregating' ? 'агрегация' : 'сканирование';
                        }
                        prog.innerHTML =
                            '<div class="progress" role="progressbar" aria-label="' + hint +
                            '" aria-valuenow="' + fill + '" aria-valuemin="0" aria-valuemax="100" title="' +
                            hint + ' · ' + fetched + '/' + total + '">' +
                            '<div class="' + barClass + '" style="width:' + fill + '%; border-radius: 0.375rem">' +
                            label + '</div></div>';
                    }
                    if (j.buckets) {
                        ['critical', 'other', 'warning', 'info'].forEach(function (k) {
                            var cell = row.querySelector('[data-sa-bucket="' + k + '"]');
                            if (cell && typeof j.buckets[k] !== 'undefined') {
                                cell.textContent = j.buckets[k];
                            }
                        });
                    }
                    if (j.started_at) {
                        var startedEl = row.querySelector('[data-sa-started]');
                        if (startedEl) startedEl.textContent = j.started_at;
                    }
                    var finishedEl = row.querySelector('[data-sa-finished]');
                    if (finishedEl) {
                        if (j.finished_at) {
                            finishedEl.textContent = j.finished_at;
                            finishedEl.removeAttribute('title');
                        } else if (j.finished) {
                            finishedEl.textContent = '—';
                            finishedEl.removeAttribute('title');
                        } else if (j.eta_at) {
                            finishedEl.innerHTML = '<span class="text-muted" title="Оценка по текущей скорости">~' + j.eta_at + '</span>';
                        } else {
                            finishedEl.innerHTML = '<span class="text-muted" title="Слишком рано для оценки">~…</span>';
                        }
                    }
                    if (j.finished) {
                        row.setAttribute('data-finished', '1');
                        row.classList.remove('cabinet-sa-row--active');
                        var actions = row.querySelector('.cabinet-sa-row-actions');
                        if (actions) {
                            // Убрать «Стоп» — иначе !querySelector('form') блокирует «Повторить»
                            actions.querySelectorAll('form').forEach(function (form) {
                                var action = (form.getAttribute('action') || '');
                                var btn = form.querySelector('button');
                                var isStop = action.indexOf('/cancel') !== -1
                                    || (btn && /Стоп/.test(btn.textContent || ''));
                                if (isStop) {
                                    form.remove();
                                }
                            });
                            if (!actions.querySelector('form[action*="/repeat"]')) {
                                var domain = (row.querySelector('td.fw-medium') || {}).textContent || 'проекта';
                                domain = String(domain).trim() || 'проекта';
                                if (j.can_resume && !actions.querySelector('form[action*="/continue"]')) {
                                    var cont = document.createElement('form');
                                    cont.method = 'POST';
                                    cont.action = '{{ url('site-audit/crawl') }}/' + j.id + '/continue';
                                    cont.className = 'd-inline';
                                    cont.setAttribute('data-cabinet-confirm',
                                        'Продолжить краул #' + j.id + ' с ' + (j.pages_fetched || 0) +
                                        ' URL? Уже скачанные страницы сохранятся.');
                                    cont.setAttribute('data-cabinet-confirm-title', 'Продолжить краул');
                                    cont.setAttribute('data-cabinet-confirm-ok', 'Продолжить');
                                    cont.innerHTML =
                                        '<input type="hidden" name="_token" value="' + token + '">' +
                                        '<button type="submit" class="btn btn-sm btn-outline-primary">Продолжить</button>';
                                    actions.appendChild(cont);
                                }
                                var repeat = document.createElement('form');
                                repeat.method = 'POST';
                                repeat.action = '{{ url('site-audit/crawl') }}/' + j.id + '/repeat';
                                repeat.className = 'd-inline';
                                repeat.setAttribute('data-cabinet-confirm',
                                    'Повторить краул для ' + domain + ' с теми же настройками? Начнётся новый краул с нуля.');
                                repeat.setAttribute('data-cabinet-confirm-title', 'Новый краул');
                                repeat.setAttribute('data-cabinet-confirm-ok', 'Повторить');
                                repeat.innerHTML =
                                    '<input type="hidden" name="_token" value="' + token + '">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-secondary">Повторить</button>';
                                actions.appendChild(repeat);

                                var del = document.createElement('form');
                                del.method = 'POST';
                                del.action = '{{ url('site-audit/crawl') }}/' + j.id;
                                del.className = 'd-inline';
                                del.setAttribute('data-cabinet-confirm', 'Удалить краул #' + j.id + '?');
                                del.setAttribute('data-cabinet-confirm-title', 'Удаление краула');
                                del.setAttribute('data-cabinet-confirm-ok', 'Удалить');
                                del.setAttribute('data-cabinet-confirm-danger', '1');
                                del.innerHTML =
                                    '<input type="hidden" name="_token" value="' + token + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>';
                                actions.appendChild(del);
                            }
                        }
                    }
                }

                function pollRow(row) {
                    var id = row.getAttribute('data-crawl-id');
                    var url = row.getAttribute('data-status-url');
                    if (!id || !url || row.getAttribute('data-finished') === '1') return;
                    if (pollTimers[id]) return;

                    function tick() {
                        if (row.getAttribute('data-finished') === '1') {
                            delete pollTimers[id];
                            return;
                        }
                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                updateRow(row, j);
                                if (j.finished) {
                                    delete pollTimers[id];
                                    return;
                                }
                                pollTimers[id] = setTimeout(tick, 2000);
                            })
                            .catch(function () {
                                pollTimers[id] = setTimeout(tick, 4000);
                            });
                    }
                    pollTimers[id] = setTimeout(tick, 800);
                }

                function pollActiveRows() {
                    if (!historyTable) return;
                    historyTable.querySelectorAll('tr[data-crawl-id][data-finished="0"]').forEach(pollRow);
                }

                document.querySelectorAll('form[data-sa-cancel-crawl]').forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var row = form.closest('tr');
                        var btn = form.querySelector('button');
                        if (btn) btn.disabled = true;
                        fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token
                            },
                            body: new FormData(form)
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                        }).then(function (x) {
                            if (!x.ok) {
                                if (btn) btn.disabled = false;
                                alert((x.j && x.j.message) ? x.j.message : 'Не удалось остановить');
                                return;
                            }
                            if (row) {
                                updateRow(row, x.j);
                            }
                        }).catch(function (err) {
                            if (btn) btn.disabled = false;
                            alert(String(err));
                        });
                    });
                });

                if (startBtn) {
                    startBtn.addEventListener('click', function () {
                        startBtn.disabled = true;
                        msg.textContent = 'Запуск…';
                        var pagesOnlyEl = document.getElementById('sa-pages-only');
                        var body = {
                            domain: document.getElementById('sa-domain').value,
                            seed_urls: document.getElementById('sa-seeds').value,
                            pages_only: pagesOnlyEl && pagesOnlyEl.checked ? '1' : '0',
                            extra_hosts: (document.getElementById('sa-extra-hosts') || {}).value || '',
                            virtual_robots: document.getElementById('sa-robots').value,
                            crawl_speed: document.getElementById('sa-speed').value,
                            concurrency: (document.getElementById('sa-concurrency') || {}).value || '1',
                            unify_www: true,
                            force_https: true,
                            strip_trailing_slash: true,
                            check_broken_links: true
                        };
                        var limitEl = document.getElementById('sa-limit');
                        if (limitEl && limitEl.value) body.pages_limit = String(saParseIntSpaces(limitEl.value) || '');

                        fetch('{{ route('pages.site-audit.start') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(body)
                        }).then(function (r) {
                            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
                        }).then(function (x) {
                            if (x.ok) {
                                msg.textContent = (x.j && x.j.message) ? x.j.message : 'Запущено';
                                var base = (x.j && x.j.redirect) ? x.j.redirect : '{{ route('pages.site-audit') }}';
                                var q = (x.j && x.j.crawl_id) ? ('?highlight=' + x.j.crawl_id) : '';
                                window.location = base + q + '#sa-history';
                                return;
                            }
                            msg.textContent = (x.j && x.j.message) ? x.j.message : 'Ошибка';
                            startBtn.disabled = false;
                        }).catch(function (e) {
                            msg.textContent = String(e);
                            startBtn.disabled = false;
                        });
                    });
                }

                pollActiveRows();

                if (window.location.hash === '#sa-history' || /[?&]highlight=/.test(window.location.search) || /[?&]domain=/.test(window.location.search)) {
                    setTimeout(scrollToHistory, 100);
                    var m = window.location.search.match(/[?&]highlight=(\d+)/);
                    if (m) {
                        var hi = historyTable && historyTable.querySelector('tr[data-crawl-id="' + m[1] + '"]');
                        if (hi) hi.classList.add('table-active');
                    }
                }
            })();
        </script>
        <script src="{{ asset('js/cabinet-site-audit-tour.js') }}?v={{ @filemtime(public_path('js/cabinet-site-audit-tour.js')) ?: time() }}"></script>
    @endslot
@endcomponent
