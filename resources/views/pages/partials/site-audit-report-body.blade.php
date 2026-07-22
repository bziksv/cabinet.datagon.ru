{{-- Тело отчёта (help + filters + table/groups + pagination). --}}
@include('pages.partials.site-audit-report-help')
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
        сохраняются <u>навсегда для этого проекта</u> (не только для текущего краула).
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
            <div class="text-muted px-3 py-3">Находок нет</div>
        @endforelse
    </div>
@else
    @php
        $colspan = 3;
        if (!empty($canIgnore)) { $colspan++; }
        if (!empty($canNote)) { $colspan++; }
    @endphp
    <div class="cabinet-sa-table-wrap">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:38%" title="Адрес страницы, где нашлась проблема. Клик — открыть сайт.">
                    URL
                    @include('pages.partials.site-audit-tip', ['tip' => "Адрес страницы с проблемой.\nНажмите ссылку — откроется сайт в новой вкладке."])
                </th>
                <th title="Насколько срочно чинить: Грубые → Прочие → Предупреждения → Инфо.">
                    Приоритет
                    @include('pages.partials.site-audit-tip', ['tip' => "Срочность.\nГрубые — чинить в первую очередь.\nИнфо — просто знать."])
                </th>
                <th title="Коротко: что именно не так (код ответа, дубль и т.п.).">
                    Детали
                    @include('pages.partials.site-audit-tip', ['tip' => "Кратко что не так: код ответа, какой дубль, какой запрос и т.д."])
                </th>
                @if(!empty($canNote))
                    <th style="width:220px" title="Ваша заметка по этой ошибке. Хранится в проекте навсегда.">
                        Комментарий / статус
                        @include('pages.partials.site-audit-tip', [
                            'tip' => "Ваша заметка к этой ошибке.\nСохраняется навсегда в проекте (не только в этом крауле).\n«Исправлено» — спрятать из счётчиков, пока не откроете снова.",
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
                        {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::metaLine($row->code ?? $code, $row->meta_json) }}
                    </td>
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
                <tr><td colspan="{{ $colspan }}" class="text-secondary px-3 py-3">Находок нет</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif

@if($pages > 1)
    <nav class="mt-3" title="Листать страницы списка">
        <ul class="pagination pagination-sm mb-0">
            @for($p = 1; $p <= $pages; $p++)
                <li class="page-item {{ $p === $page ? 'active' : '' }}">
                    <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a>
                </li>
            @endfor
        </ul>
    </nav>
@endif
