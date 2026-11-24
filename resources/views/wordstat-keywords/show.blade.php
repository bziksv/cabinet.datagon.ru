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

        @include('pages.partials.module-beta-banner', ['moduleName' => __('Wordstat keywords')])

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
                <i class="bi bi-file-earmark-excel"></i> {{ __('Скачать все фразы (Excel)') }}
            </a>
        </div>

        <section class="cabinet-wsk-collect card card-body mb-3">
            <div class="row g-4">

                {{-- Шаг 1: фразы --}}
                <div class="col-lg-6">
                    <h6 class="cabinet-wsk-step">{{ __('1. Какие фразы собрать') }}</h6>
                    <label class="visually-hidden" for="wskText">{{ __('Фразы') }}</label>
                    <textarea class="form-control cabinet-wsk-text" id="wskText" rows="9" data-wsk-text
                              placeholder="{{ __('пластиковые окна') }}&#10;{{ __('Окна: пластиковые окна') }}&#10;# {{ __('Потолки') }}&#10;{{ __('натяжные потолки') }}"></textarea>
                    <div class="form-text">
                        {{ __('Каждая строка — одна фраза, которую будем искать в Wordstat.') }}
                        {{ __('Чтобы разложить фразы по группам, напишите название группы и двоеточие: «Окна: пластиковые окна».') }}
                        {{ __('Или поставьте строку «# Потолки» — все фразы под ней пойдут в группу «Потолки», пока не встретится следующая такая строка.') }}
                    </div>
                    <div class="form-text">
                        {{ __('Из Excel можно вставить два столбца: в первом название группы, во втором фраза.') }}
                        {{ __('Фразы длиннее 400 символов или с больше чем 7 словами пропустим — список пропущенных покажем при подсчёте.') }}
                    </div>
                    <div class="small mt-2" data-wsk-default-group-hint></div>
                </div>

                {{-- Шаг 2: что брать из Wordstat --}}
                <div class="col-lg-6">
                    <h6 class="cabinet-wsk-step">{{ __('2. Что брать из Wordstat') }}</h6>

                    <div class="cabinet-wsk-option">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="wskColLeft" value="left" data-wsk-column>
                            <label class="form-check-label" for="wskColLeft">{{ __('Запросы, где есть ваша фраза') }}</label>
                        </div>
                        <div class="form-text">{{ __('Для фразы «окна» — например, «пластиковые окна цена», «окна на заказ».') }}</div>
                    </div>

                    <div class="cabinet-wsk-option">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="wskColRight" value="right" data-wsk-column>
                            <label class="form-check-label" for="wskColRight">{{ __('Похожие запросы') }}</label>
                        </div>
                        <div class="form-text">{{ __('Другие запросы на ту же тему — например, для «окна» это «балконные двери», «остекление».') }}</div>
                    </div>

                    <div class="cabinet-wsk-option">
                        <label class="form-label mb-1" for="wskLeftLimit">{{ __('Сколько фраз брать на каждую вашу фразу') }}</label>
                        <input type="text" inputmode="numeric" id="wskLeftLimit" class="form-control form-control-sm sa-num-space" data-wsk-setting="left_limit" style="max-width: 180px">
                        <div class="form-text">{{ __('От 1 до 2 000. Чем больше, тем больше фраз и запросов.') }}</div>
                    </div>
                </div>

                {{-- Шаг 3: регион, устройство, шаблон --}}
                <div class="col-lg-6">
                    <h6 class="cabinet-wsk-step">{{ __('3. Где и с каких устройств считать') }}</h6>
                    <div class="cabinet-wsk-option">
                        <label class="form-label mb-1">{{ __('Регион') }}</label>
                        <select class="form-select" data-wsk-region-select data-wsk-setting="region_ids" multiple></select>
                        <div class="form-text">{{ __('Частоту считаем по выбранным регионам. По умолчанию — Россия.') }}</div>
                    </div>
                    <div class="cabinet-wsk-option">
                        <label class="form-label mb-1" for="wskDevice">{{ __('Устройство') }}</label>
                        <select class="form-select" id="wskDevice" data-wsk-setting="device">
                            <option value="all">{{ __('Все устройства') }}</option>
                            <option value="desktop">{{ __('Компьютеры') }}</option>
                            <option value="phone">{{ __('Телефоны') }}</option>
                            <option value="tablet">{{ __('Планшеты') }}</option>
                        </select>
                        <div class="form-text">{{ __('Если сайт нужен всем пользователям — оставьте «Все устройства».') }}</div>
                    </div>
                </div>

                {{-- Шаг 4: шаблон настроек --}}
                <div class="col-lg-6">
                    <h6 class="cabinet-wsk-step">{{ __('4. Шаблон настроек (необязательно)') }}</h6>
                    <div class="cabinet-wsk-option">
                        <label class="form-label mb-1" for="wskPreset">{{ __('Сохранённые настройки') }}</label>
                        <div class="cabinet-wsk-preset-row">
                            <div class="flex-grow-1"><select class="form-select" id="wskPreset" data-wsk-preset><option value=""></option></select></div>
                            <button type="button" class="btn btn-outline-danger" data-wsk-preset-delete aria-label="{{ __('Удалить шаблон') }}" disabled><i class="bi bi-trash"></i></button>
                        </div>
                        <div class="form-text">{{ __('Выберите шаблон — его настройки подставятся в форму. Шаблон можно сохранить из текущих настроек (кнопка внизу). Шаблоны команды видят все её участники; удалить можно только свои.') }}</div>
                    </div>
                </div>

                {{-- Дополнительно --}}
                <div class="col-12">
                    <details class="cabinet-wsk-advanced">
                        <summary>{{ __('Дополнительно: расширение списка и исключения') }}</summary>
                        <div class="row g-4 mt-0 pt-3">
                            <div class="col-md-6">
                                <div class="cabinet-wsk-option">
                                    <label class="form-label mb-1" for="wskDepth">{{ __('Расширить список') }}</label>
                                    <select class="form-select" id="wskDepth" data-wsk-setting="depth">
                                        <option value="0">{{ __('Не расширять') }}</option>
                                        <option value="1">{{ __('Один круг') }}</option>
                                        <option value="2">{{ __('Два круга') }}</option>
                                    </select>
                                    <div class="form-text">{{ __('Каждую найденную фразу проверим в Wordstat ещё раз и возьмём из неё новые фразы. Фраз станет больше, запросов тоже.') }}</div>
                                </div>
                                <div class="cabinet-wsk-option">
                                    <label class="form-label mb-1" for="wskDepthLeft">{{ __('Фраз на каждую найденную') }}</label>
                                    <input type="text" inputmode="numeric" id="wskDepthLeft" class="form-control form-control-sm sa-num-space" data-wsk-setting="depth_left_limit" style="max-width: 180px">
                                    <div class="form-text">{{ __('От 1 до 200.') }}</div>
                                </div>
                                <div class="cabinet-wsk-option">
                                    <label class="form-label mb-1" for="wskDepthMax">{{ __('Сколько найденных фраз расширять всего') }}</label>
                                    <input type="text" inputmode="numeric" id="wskDepthMax" class="form-control form-control-sm sa-num-space" data-wsk-setting="depth_max_seeds" style="max-width: 180px">
                                    <div class="form-text">{{ __('Ограничивает расход запросов. От 0 до 1 000.') }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="cabinet-wsk-option">
                                    <label class="form-label mb-1" for="wskMinus">{{ __('Исключить фразы со словами') }}</label>
                                    <textarea class="form-control form-control-sm" id="wskMinus" rows="2" data-wsk-setting="minus_words" placeholder="{{ __('бесплатно, своими руками') }}"></textarea>
                                    <div class="form-text">{{ __('Фразы с этими словами не попадут в список. Через запятую или с новой строки.') }}</div>
                                </div>
                                <div class="cabinet-wsk-option">
                                    <label class="form-label mb-1" for="wskStop">{{ __('Не отправлять в Wordstat эти слова') }}</label>
                                    <textarea class="form-control form-control-sm" id="wskStop" rows="2" data-wsk-setting="stop_words" placeholder="{{ __('купить') }}"></textarea>
                                    <div class="form-text">{{ __('Эти слова уберём из вашей фразы перед запросом. Сама фраза в список попадёт целиком.') }}</div>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <div class="cabinet-wsk-preview mt-4" data-wsk-preview hidden></div>

            <div class="cabinet-wsk-actions mt-4">
                <button type="button" class="btn btn-primary" data-wsk-run-btn>{{ __('Запустить сбор') }}</button>
                <button type="button" class="btn btn-outline-primary" data-wsk-preview-btn>{{ __('Посчитать без сбора') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-wsk-preset-save>{{ __('Сохранить настройки как шаблон') }}</button>
            </div>
            <div class="form-text mt-2">
                {{ __('«Посчитать» ничего не собирает: покажет, сколько фраз попадёт в сбор и сколько запросов к Wordstat это займёт. Сбор идёт в фоне — можно закрыть страницу.') }}
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
                        <button type="button" class="btn btn-outline-secondary active" data-wsk-status="active">{{ __('Список') }}</button>
                        <button type="button" class="btn btn-outline-secondary" data-wsk-status="minus">{{ __('Исключённые') }}</button>
                    </div>
                </div>
                <div class="form-text mb-2">{{ __('Нажмите группу, чтобы увидеть её фразы. Новые фразы без группы пойдут в выбранную здесь группу.') }}</div>
                <div data-wsk-groups></div>
            </aside>
            <section class="cabinet-wsk-table">
                <div class="cabinet-wsk-toolbar">
                    <input type="search" class="form-control form-control-sm cabinet-wsk-toolbar__search" placeholder="{{ __('Поиск по фразам') }}" data-wsk-search>
                    <select class="form-select form-select-sm w-auto" data-wsk-bulk-action>
                        <option value="">{{ __('Действие с отмеченными…') }}</option>
                        <option value="move">{{ __('Перенести в группу') }}</option>
                        <option value="minus" data-wsk-for-status="active">{{ __('Исключить из списка') }}</option>
                        <option value="restore" data-wsk-for-status="minus" hidden>{{ __('Вернуть в список') }}</option>
                        <option value="delete">{{ __('Удалить навсегда') }}</option>
                    </select>
                    <div class="cabinet-wsk-toolbar__group" data-wsk-bulk-group-wrap hidden>
                        <select class="form-select form-select-sm" data-wsk-bulk-group></select>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-wsk-bulk-apply>{{ __('Применить') }}</button>
                    <span class="small text-muted ms-auto" data-wsk-counter></span>
                    <a class="btn btn-sm btn-outline-secondary" href="#" data-wsk-export-group hidden><i class="bi bi-file-earmark-excel"></i> {{ __('Excel: только эта группа') }}</a>
                </div>
                <div class="table-responsive cabinet-wsk-table__scroll">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr>
                            <th class="cabinet-wsk-check"><input type="checkbox" class="form-check-input" data-wsk-check-all aria-label="{{ __('Выбрать все') }}"></th>
                            <th class="cabinet-wsk-sortable" data-wsk-sort="phrase">{{ __('Фраза') }} <span class="cabinet-wsk-sort-mark"></span></th>
                            <th class="cabinet-wsk-sortable text-end" data-wsk-sort="freq">{{ __('Частота в Wordstat') }} <span class="cabinet-wsk-sort-mark"></span></th>
                            <th>{{ __('Откуда') }}</th>
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
