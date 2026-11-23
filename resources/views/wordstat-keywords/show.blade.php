@php
    $wskVersion = config('cabinet-wordstat-keywords.version');
    $wskUserId = (int) auth()->id();
    $wskData = [
        'groups' => $groups->map(fn ($g) => ['id' => (int) $g->id, 'name' => $g->name, 'count' => (int) $g->phrases_count])->values(),
        'presets' => $presets->map(fn ($p) => [
            'id' => (int) $p->id,
            'title' => $p->title,
            'settings' => \App\Services\Wordstat\WordstatSettings::normalize((array) $p->settings),
            'team_id' => $p->team_id,
            'mine' => (int) $p->user_id === $wskUserId,
        ])->values(),
        'activeRun' => $activeRun,
        'regions' => $regions,
        'defaults' => \App\Services\Wordstat\WordstatSettings::normalize(['region_ids' => (array) $project->region_ids]),
        'defaultGroup' => \App\Wordstat\WordstatGroup::DEFAULT_NAME,
    ];
    $wskRunStatuses = [
        'queued' => __('в очереди'),
        'running' => __('идёт'),
        'done' => __('готово'),
        'cancelled' => __('остановлен'),
        'failed' => __('ошибка'),
    ];
@endphp
@component('component.card', [
    'title' => __('Wordstat keywords'),
    'titleHtml' => e(__('Wordstat keywords')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-wordstat-keywords'])->render(),
    'documentTitle' => $project->domain . ' — ' . __('Wordstat keywords'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-wordstat-keywords.css') }}?v={{ $wskVersion }}.{{ @filemtime(public_path('css/cabinet-wordstat-keywords.css')) ?: time() }}">
    @endslot

    <div class="cabinet-wsk-page" id="cabinetWskPage"
         data-preview-url="{{ route('pages.wordstat-keywords.preview', $project->id) }}"
         data-run-url="{{ route('pages.wordstat-keywords.runs.store', $project->id) }}"
         data-run-status-url="{{ url('/wordstat-keywords/' . $project->id . '/runs') }}"
         data-phrases-url="{{ route('pages.wordstat-keywords.phrases', $project->id) }}"
         data-bulk-url="{{ route('pages.wordstat-keywords.phrases.bulk', $project->id) }}"
         data-groups-url="{{ url('/wordstat-keywords/' . $project->id . '/groups') }}"
         data-export-url="{{ route('pages.wordstat-keywords.export', $project->id) }}"
         data-preset-store-url="{{ route('pages.wordstat-keywords.presets.store') }}"
         data-preset-delete-url="{{ url('/wordstat-keywords/presets') }}"
         data-regions-url="{{ route('cluster.regions') }}"
         data-csrf="{{ csrf_token() }}">

        <script type="application/json" id="cabinetWskData">{!! json_encode($wskData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

        <div class="cabinet-wsk-head">
            <div>
                <a href="{{ route('pages.wordstat-keywords') }}" class="cabinet-wsk-back"><i class="bi bi-arrow-left"></i> {{ __('Все проекты') }}</a>
                <div class="cabinet-wsk-head__title">
                    <strong>{{ $project->domain }}</strong>
                    @if ($project->title)<span class="text-muted"> · {{ $project->title }}</span>@endif
                </div>
                <div class="text-muted small">{{ __('Запросов к Wordstat в этом месяце') }}: <strong>{{ number_format((int) $monthRequests, 0, '', ' ') }}</strong></div>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('pages.wordstat-keywords.export', $project->id) }}">
                <i class="bi bi-file-earmark-excel"></i> {{ __('Выгрузить XLSX') }}
            </a>
        </div>

        <section class="cabinet-wsk-collect card card-body mb-3">
            <div class="row g-3">
                <div class="col-lg-7">
                    <label class="form-label" for="wskText">
                        {{ __('Фразы') }}
                        @include('pages.partials.site-audit-tip', ['tip' => __('Одна строка — одна фраза. Группа: «Группа: фраза», строка «# Группа» для блока фраз ниже, или две колонки из Excel (группа, фраза). Фраза — не длиннее 400 символов и не больше 7 слов.')])
                    </label>
                    <textarea class="form-control cabinet-wsk-text" id="wskText" rows="10" data-wsk-text placeholder="Окна: пластиковые окна&#10;# Потолки&#10;натяжные потолки"></textarea>
                    <div class="small text-muted mt-1" data-wsk-default-group-hint></div>
                </div>
                <div class="col-lg-5">
                    <label class="form-label">
                        {{ __('Пресет') }}
                        @include('pages.partials.site-audit-tip', ['tip' => __('Пресет — сохранённый набор настроек сбора. Пресеты команды доступны всем её участникам, удалить можно только свои.')])
                    </label>
                    <div class="cabinet-wsk-preset-row mb-2">
                        <div class="flex-grow-1"><select class="form-select" data-wsk-preset><option value=""></option></select></div>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-wsk-preset-delete aria-label="{{ __('Удалить пресет') }}" disabled><i class="bi bi-trash"></i></button>
                    </div>
                    <label class="form-label">{{ __('Регионы') }}</label>
                    <div class="mb-2"><select class="form-select" data-wsk-region-select data-wsk-setting="region_ids" multiple></select></div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="wskDevice">{{ __('Устройства') }}</label>
                            <select class="form-select form-select-sm" id="wskDevice" data-wsk-setting="device">
                                <option value="all">{{ __('Все') }}</option>
                                <option value="desktop">{{ __('Компьютеры') }}</option>
                                <option value="phone">{{ __('Телефоны') }}</option>
                                <option value="tablet">{{ __('Планшеты') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="wskLeftLimit">
                                {{ __('Фраз из левой колонки') }}
                                @include('pages.partials.site-audit-tip', ['tip' => __('Сколько фраз брать из левой колонки Wordstat на каждую вашу фразу: от 1 до 2 000.')])
                            </label>
                            <input type="text" inputmode="numeric" id="wskLeftLimit" class="form-control form-control-sm sa-num-space" data-wsk-setting="left_limit">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="wskColLeft" value="left" data-wsk-column>
                                <label class="form-check-label" for="wskColLeft">{{ __('Левая колонка') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="wskColRight" value="right" data-wsk-column>
                                <label class="form-check-label" for="wskColRight">{{ __('Правая колонка') }}</label>
                            </div>
                            @include('pages.partials.site-audit-tip', ['tip' => __('Левая колонка — запросы, содержащие вашу фразу. Правая — похожие запросы.')])
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="wskDepth">
                                {{ __('Глубина') }}
                                @include('pages.partials.site-audit-tip', ['tip' => __('0 — только ваши фразы. 1–2 — дособрать по найденным фразам.')])
                            </label>
                            <select class="form-select form-select-sm" id="wskDepth" data-wsk-setting="depth">
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="wskDepthLeft">
                                {{ __('Фраз при досборе') }}
                                @include('pages.partials.site-audit-tip', ['tip' => __('Сколько фраз брать на каждую найденную фразу при досборе: от 1 до 200.')])
                            </label>
                            <input type="text" inputmode="numeric" id="wskDepthLeft" class="form-control form-control-sm sa-num-space" data-wsk-setting="depth_left_limit">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="wskDepthMax">
                                {{ __('Лимит досбора') }}
                                @include('pages.partials.site-audit-tip', ['tip' => __('Сколько найденных фраз максимум дособрать за запуск. Ограничивает расход запросов.')])
                            </label>
                            <input type="text" inputmode="numeric" id="wskDepthMax" class="form-control form-control-sm sa-num-space" data-wsk-setting="depth_max_seeds">
                        </div>
                    </div>
                    <label class="form-label mt-2" for="wskMinus">
                        {{ __('Минус-слова') }}
                        @include('pages.partials.site-audit-tip', ['tip' => __('Найденные фразы с этими словами не попадут в группы. Через запятую или с новой строки.')])
                    </label>
                    <textarea class="form-control form-control-sm" id="wskMinus" rows="2" data-wsk-setting="minus_words" placeholder="бесплатно, своими руками"></textarea>
                    <label class="form-label mt-2" for="wskStop">
                        {{ __('Стоп-слова') }}
                        @include('pages.partials.site-audit-tip', ['tip' => __('Слова вырезаются из ваших фраз перед запросом, например «купить».')])
                    </label>
                    <textarea class="form-control form-control-sm" id="wskStop" rows="1" data-wsk-setting="stop_words"></textarea>
                </div>
            </div>

            <div class="cabinet-wsk-preview mt-3" data-wsk-preview hidden></div>

            <div class="cabinet-wsk-actions mt-3">
                <button type="button" class="btn btn-outline-primary" data-wsk-preview-btn>{{ __('Проверить') }}</button>
                <button type="button" class="btn btn-primary" data-wsk-run-btn>{{ __('Собрать') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-wsk-preset-save>{{ __('Сохранить как пресет') }}</button>
            </div>

            <div class="cabinet-wsk-progress mt-3" data-wsk-progress hidden>
                <div class="progress"><div class="progress-bar" role="progressbar" style="width:0%"></div></div>
                <div class="cabinet-wsk-progress__row">
                    <div class="small text-muted" data-wsk-progress-text></div>
                    <button type="button" class="btn btn-link btn-sm text-danger p-0" data-wsk-cancel>{{ __('Остановить') }}</button>
                </div>
            </div>

            @if ($lastRuns->isNotEmpty())
                <details class="cabinet-wsk-runs mt-3">
                    <summary class="small text-muted">{{ __('Последние запуски') }}</summary>
                    <table class="table table-sm mb-0 mt-2">
                        <thead>
                        <tr>
                            <th>{{ __('Дата') }}</th>
                            <th>{{ __('Статус') }}</th>
                            <th class="text-end">{{ __('Фраз обработано') }}</th>
                            <th class="text-end">{{ __('Новых фраз') }}</th>
                            <th class="text-end">{{ __('Запросов') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($lastRuns as $run)
                            <tr>
                                <td>{{ optional($run->created_at)->format('d.m.Y H:i') }}</td>
                                <td>{{ $wskRunStatuses[$run->status] ?? $run->status }}</td>
                                <td class="text-end">{{ number_format((int) $run->seeds_done, 0, '', ' ') }} / {{ number_format((int) $run->seeds_total, 0, '', ' ') }}</td>
                                <td class="text-end">{{ number_format((int) $run->phrases_added, 0, '', ' ') }}</td>
                                <td class="text-end">{{ number_format((int) $run->requests_used, 0, '', ' ') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>
            @endif
        </section>

        <div class="cabinet-wsk-body">
            <aside class="cabinet-wsk-groups">
                <div class="cabinet-wsk-groups__head">
                    <span>{{ __('Группы') }}</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Какие фразы показать') }}">
                        <button type="button" class="btn btn-outline-secondary active" data-wsk-status="active">{{ __('Рабочие') }}</button>
                        <button type="button" class="btn btn-outline-secondary" data-wsk-status="minus">{{ __('Минус') }}</button>
                    </div>
                </div>
                <div data-wsk-groups></div>
            </aside>
            <section class="cabinet-wsk-table">
                <div class="cabinet-wsk-toolbar">
                    <input type="search" class="form-control form-control-sm cabinet-wsk-toolbar__search" placeholder="{{ __('Поиск по фразам') }}" data-wsk-search>
                    <select class="form-select form-select-sm w-auto" data-wsk-bulk-action>
                        <option value="">{{ __('С выбранными…') }}</option>
                        <option value="move">{{ __('Перенести в группу') }}</option>
                        <option value="minus" data-wsk-for-status="active">{{ __('В минус') }}</option>
                        <option value="restore" data-wsk-for-status="minus" hidden>{{ __('Вернуть в работу') }}</option>
                        <option value="delete">{{ __('Удалить') }}</option>
                    </select>
                    <div class="cabinet-wsk-toolbar__group" data-wsk-bulk-group-wrap hidden>
                        <select class="form-select form-select-sm" data-wsk-bulk-group></select>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-wsk-bulk-apply>{{ __('Применить') }}</button>
                    <span class="small text-muted ms-auto" data-wsk-counter></span>
                    <a class="btn btn-sm btn-outline-secondary" href="#" data-wsk-export-group hidden><i class="bi bi-file-earmark-excel"></i> {{ __('XLSX группы') }}</a>
                </div>
                <div class="table-responsive cabinet-wsk-table__scroll">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr>
                            <th class="cabinet-wsk-check"><input type="checkbox" class="form-check-input" data-wsk-check-all aria-label="{{ __('Выбрать все') }}"></th>
                            <th class="cabinet-wsk-sortable" data-wsk-sort="phrase">{{ __('Фраза') }} <span class="cabinet-wsk-sort-mark"></span></th>
                            <th class="cabinet-wsk-sortable text-end" data-wsk-sort="freq">{{ __('Частотность') }} <span class="cabinet-wsk-sort-mark"></span></th>
                            <th>{{ __('Источник') }}</th>
                        </tr>
                        </thead>
                        <tbody data-wsk-rows></tbody>
                    </table>
                </div>
                <div class="cabinet-wsk-table__empty text-muted small" data-wsk-empty hidden></div>
            </section>
        </div>

        <div class="modal fade" id="cabinetWskPromptModal" tabindex="-1" aria-hidden="true" aria-labelledby="cabinetWskPromptTitle">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" data-wsk-prompt-form>
                    <div class="modal-header">
                        <h5 class="modal-title" id="cabinetWskPromptTitle" data-wsk-prompt-title></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Закрыть') }}"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="cabinetWskPromptInput" data-wsk-prompt-label></label>
                        <input type="text" class="form-control" id="cabinetWskPromptInput" maxlength="255" data-wsk-prompt-input required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Отмена') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Сохранить') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="cabinetWskMergeModal" tabindex="-1" aria-hidden="true" aria-labelledby="cabinetWskMergeTitle">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" data-wsk-merge-form>
                    <div class="modal-header">
                        <h5 class="modal-title" id="cabinetWskMergeTitle">{{ __('Объединить группы') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Закрыть') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted" data-wsk-merge-text></p>
                        <label class="form-label">{{ __('Перенести фразы в группу') }}</label>
                        <select class="form-select" data-wsk-merge-target><option value=""></option></select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Отмена') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Объединить') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('partials.cabinet-confirm-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-ajax-form.js') }}?v={{ @filemtime(public_path('js/cabinet-ajax-form.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-wordstat-keywords.js') }}?v={{ $wskVersion }}.{{ @filemtime(public_path('js/cabinet-wordstat-keywords.js')) ?: time() }}"></script>
    @endslot
@endcomponent
