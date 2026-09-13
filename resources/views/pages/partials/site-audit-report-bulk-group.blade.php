{{-- Массовые действия для одного блока в режиме «По ошибкам». --}}
@php
    $groupFindingIds = array_values(array_filter(array_map('intval', is_array($group['finding_ids'] ?? null) ? $group['finding_ids'] : [])));
    $groupBulkCount = count($groupFindingIds);
    $groupBulkLabel = number_format(max($groupBulkCount, (int) ($group['size'] ?? 0)), 0, '', ' ');
    $groupBulkCsv = $groupBulkCount > 0 ? implode(',', $groupFindingIds) : '';
    $groupHash = (string) ($group['hash'] ?? '');
    $groupLabel = (string) ($group['label'] ?? '');
    $usesSharedFindings = \App\Services\SiteAudit\SiteAuditDuplicateGrouper::usesSharedFindings((string) ($code ?? ''));
    $canBulkGroup = $usesSharedFindings
        ? ($groupHash !== '')
        : ($groupBulkCount > 0);
    $isShowIgnored = !empty($showIgnored);
    $isShowFixed = !empty($showFixed);
@endphp
@if($canBulkGroup && (!empty($canNote) || !empty($canIgnore)))
    <div class="cabinet-sa-dup-group__actions" role="group" aria-label="Действия для всего блока ({{ $groupBulkLabel }})">
        @if($isShowIgnored && !empty($canIgnore) && $usesSharedFindings && $groupHash !== '')
            <form method="POST"
                  action="{{ route('pages.site-audit.ignore.restore', $crawl->id) }}"
                  class="cabinet-sa-dup-group__act-form"
                  data-cabinet-confirm="Вернуть блок из игнора?"
                  data-cabinet-confirm-title="Вернуть блок"
                  data-cabinet-confirm-ok="Вернуть">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <input type="hidden" name="scope" value="pattern">
                <input type="hidden" name="group_hash" value="{{ $groupHash }}">
                <button type="submit" class="btn btn-sm btn-outline-primary cabinet-sa-dup-group__act-btn">
                    <i class="fa fa-undo" aria-hidden="true"></i>
                    Вернуть · блок
                </button>
            </form>
        @elseif($isShowFixed && !empty($canNote) && $usesSharedFindings && $groupHash !== '')
            <form method="POST"
                  action="{{ route('pages.site-audit.note.clear-pattern', $crawl->id) }}"
                  class="cabinet-sa-dup-group__act-form"
                  data-cabinet-confirm="Снова показать блок в замечаниях?"
                  data-cabinet-confirm-title="Открыть блок"
                  data-cabinet-confirm-ok="Открыть">
                @csrf
                <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="code" value="{{ $code }}">
                <input type="hidden" name="group_hash" value="{{ $groupHash }}">
                <button type="submit" class="btn btn-sm btn-outline-primary cabinet-sa-dup-group__act-btn">
                    <i class="fa fa-undo" aria-hidden="true"></i>
                    Открыть · блок
                </button>
            </form>
        @else
            @if(!empty($canNote) && ! $isShowIgnored)
                <form method="POST"
                      action="{{ route('pages.site-audit.note.bulk-fixed', $crawl->id) }}"
                      class="cabinet-sa-dup-group__act-form"
                      data-cabinet-confirm="Пометить весь блок ({{ $groupBulkLabel }} стр.) как исправленный?{{ $usesSharedFindings ? ' Другие блоки на тех же страницах не затронутся.' : '' }}"
                      data-cabinet-confirm-title="Исправлено · блок"
                      data-cabinet-confirm-ok="Пометить">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                    <input type="hidden" name="code" value="{{ $code }}">
                    <input type="hidden" name="bulk_scope" value="group">
                    <input type="hidden" name="group_hash" value="{{ $groupHash }}">
                    <input type="hidden" name="group_label" value="{{ \Illuminate\Support\Str::limit($groupLabel, 120, '') }}">
                    @if(! $usesSharedFindings)
                        <input type="hidden" name="finding_ids_csv" value="{{ $groupBulkCsv }}">
                    @endif
                    <button type="submit" class="btn btn-sm btn-outline-success cabinet-sa-dup-group__act-btn">
                        <i class="fa fa-check" aria-hidden="true"></i>
                        Исправлено · блок
                    </button>
                    @include('pages.partials.site-audit-tip', [
                        'tip' => $usesSharedFindings
                            ? "Пометить только этот блок/паттерн (не все HTML-ошибки на тех же страницах).\nДругие блоки на странице останутся. Вернуть: «Показать исправленные»."
                            : "Пометить все URL этого блока как исправленные (не только видимый список из 10).\nУйдут из счётчиков. Вернуть: «Показать исправленные» → «Открыть».",
                        'tipSide' => 'left',
                    ])
                </form>
            @endif
            @if(!empty($canIgnore) && ! $isShowFixed)
                <form method="POST"
                      action="{{ route('pages.site-audit.ignore.bulk', $crawl->id) }}"
                      class="cabinet-sa-dup-group__act-form"
                      data-cabinet-confirm="Добавить весь блок ({{ $groupBulkLabel }} стр.) в игнор? Другие блоки на тех же страницах не затронутся."
                      data-cabinet-confirm-title="Игнор · блок"
                      data-cabinet-confirm-ok="В игнор"
                      data-cabinet-confirm-danger="1">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
                    <input type="hidden" name="code" value="{{ $code }}">
                    <input type="hidden" name="bulk_scope" value="group">
                    <input type="hidden" name="group_hash" value="{{ $groupHash }}">
                    <input type="hidden" name="group_label" value="{{ \Illuminate\Support\Str::limit($groupLabel, 120, '') }}">
                    @if(! $usesSharedFindings)
                        <input type="hidden" name="finding_ids_csv" value="{{ $groupBulkCsv }}">
                    @endif
                    <button type="submit" class="btn btn-sm btn-outline-secondary cabinet-sa-dup-group__act-btn">
                        <i class="fa fa-ban" aria-hidden="true"></i>
                        Игнор · блок
                    </button>
                    @include('pages.partials.site-audit-tip', [
                        'tip' => $usesSharedFindings
                            ? "В игнор только этот блок/паттерн (например «Tag use invalid»).\nОстальные ошибки на тех же URL останутся. Снять: «Показать игнор» → «Вернуть · блок»."
                            : "Добавить все URL этого блока в игнор (не только видимый список).\nКак «Игнор» у строки: не ошибка / ложное срабатывание, в т.ч. в следующих проверках.\nСнять: «Показать игнор» → «Вернуть».",
                        'tipSide' => 'left',
                    ])
                </form>
            @endif
        @endif
    </div>
@endif
