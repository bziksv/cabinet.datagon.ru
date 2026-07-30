@component('component.card', ['title' => __('My project')])
    @slot('css')
        @include('backlink.partials.styles')
    @endslot

    @php
        $linksTotal = (int) ($project->total_link ?? $project->link->count());
        $linksBroken = (int) ($project->total_broken_link ?? 0);
    @endphp

    <div class="cabinet-backlink-page">
        @include('backlink.partials.toasts')
        @include('backlink.partials.module-nav', ['active' => 'show', 'project' => $project])

        <div class="d-flex flex-column gap-2">
            @include('backlink.partials.free-tariff-email-notice')
            @include('partials.cabinet-telegram-notify-notice', ['extraClass' => 'cabinet-bl-telegram-notice'])
        </div>

        <div class="cabinet-bl-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-bl-lead__icon" aria-hidden="true">
                    <i class="bi bi-folder2-open"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ $project->project_name }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Backlink show lead hint') }}</p>
                </div>
            </div>
        </div>

        <div class="alert alert-info cabinet-bl-schedule-alert mb-0" role="status">
            <div class="d-flex gap-2 align-items-start">
                <i class="bi bi-clock-history mt-1" aria-hidden="true"></i>
                <div>
                    <p class="mb-1 fw-semibold">{{ __('Backlink schedule title') }}</p>
                    <ul class="mb-0 small ps-3">
                        <li>{{ __('Backlink schedule after upload') }}</li>
                        <li>{{ __('Backlink schedule full scan', ['time' => $schedule['full_scan'] ?? '01:00']) }}</li>
                        <li>{{ __('Backlink schedule broken scan') }}</li>
                        <li>{{ __('Backlink schedule manual') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        @php
            $checkBusy = in_array(($checkProgress['status'] ?? ''), ['queued', 'running'], true);
            $checkDone = ($checkProgress['status'] ?? '') === 'done';
            $checkTotal = (int) ($checkProgress['total'] ?? 0);
            $checkDoneCount = (int) ($checkProgress['done'] ?? 0);
            $checkPct = $checkTotal > 0 ? min(100, (int) round(100 * $checkDoneCount / $checkTotal)) : 0;
        @endphp

        <div id="cabinet-bl-check-progress"
             class="card border shadow-sm mb-0{{ $checkBusy || $checkDone ? '' : ' d-none' }}"
             data-status-url="{{ route('check.project.links.status', $project->id) }}"
             data-busy="{{ $checkBusy ? '1' : '0' }}">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <span class="fw-semibold small" id="cabinet-bl-check-progress-label">
                        @if($checkBusy)
                            {{ __('Backlink check project progress', ['done' => $checkDoneCount, 'total' => $checkTotal]) }}
                        @elseif($checkDone)
                            {{ __('Backlink check project finished', ['total' => $checkTotal]) }}
                        @endif
                    </span>
                    @if($checkDone)
                        <a href="{{ route('show.backlink', $project->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Backlink check project reload') }}</a>
                    @endif
                </div>
                <div class="progress" style="height: 0.5rem;">
                    <div id="cabinet-bl-check-progress-bar"
                         class="progress-bar{{ $checkBusy ? ' progress-bar-striped progress-bar-animated' : '' }}"
                         role="progressbar"
                         style="width: {{ $checkPct }}%;"
                         aria-valuenow="{{ $checkPct }}"
                         aria-valuemin="0"
                         aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <div class="card cabinet-bl-project-card border shadow-sm mb-0">
            <div class="card-header py-2">
                <h4 class="card-title h6 mb-0">{{ __('Backlink project settings') }}</h4>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-6">
                        <label class="form-label" for="cabinet-bl-project-name">{{ __('Project name') }}</label>
                        <input type="text"
                               name="project_name"
                               id="cabinet-bl-project-name"
                               class="form-control cabinet-bl-project-name"
                               value="{{ $project->project_name }}"
                               data-project-id="{{ $project->id }}">
                    </div>
                    <div class="col-md-6">
                        @include('backlink.partials.monitoring-field', [
                            'options' => $monitoring,
                            'value' => $project->monitoring_project_id,
                            'class' => ['form-select'],
                            'wrapperClass' => 'mb-0',
                            'fieldId' => 'monitoring_project_id_show',
                            'projectId' => $project->id,
                        ])
                    </div>
                </div>

                <div class="cabinet-bl-kpi-row mt-3">
                    <div class="cabinet-bl-kpi">
                        <div class="cabinet-bl-kpi__value">{{ number_format($linksTotal, 0, ',', ' ') }}</div>
                        <div class="cabinet-bl-kpi__label">{{ __('Backlink links total') }}</div>
                    </div>
                    <div class="cabinet-bl-kpi{{ $linksBroken > 0 ? ' cabinet-bl-kpi--danger' : '' }}"
                         @if($linksBroken > 0) role="button" tabindex="0" data-bl-quick-filter="broken" title="{{ __('Backlink filter broken') }}" @endif>
                        <div class="cabinet-bl-kpi__value">{{ number_format($linksBroken, 0, ',', ' ') }}</div>
                        <div class="cabinet-bl-kpi__label">{{ __('Backlink links broken') }}</div>
                        <div class="cabinet-bl-kpi__hint small text-secondary">{{ __('Backlink links broken hint') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cabinet-bl-toolbar">
            <p class="mb-0 small text-secondary">{{ __('Backlink links in project') }}</p>
            <div class="cabinet-bl-toolbar__actions">
                @if($linksTotal > 0)
                    <form action="{{ route('check.project.links', $project->id) }}"
                          method="post"
                          class="d-inline"
                          onsubmit='return confirm(@json(__('Backlink check project confirm', ['count' => $linksTotal])))'>
                        @csrf
                        <button type="submit"
                                class="btn btn-outline-primary"
                                @if($checkBusy) disabled @endif
                                title="{{ __('Backlink check project') }}">
                            <i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('Backlink check project') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('add.link.view', $project->id) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add link') }}
                </a>
                <a href="{{ route('backlink') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('To my projects') }}
                </a>
            </div>
        </div>

        @if($linksTotal === 0)
            <div class="cabinet-bl-empty">
                <i class="bi bi-link-45deg display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
                <p class="mb-0">{{ __('Backlink empty links') }}</p>
            </div>
        @else
            <div class="cabinet-bl-filters card border shadow-sm mb-0" id="cabinet-bl-filters">
                <div class="card-body py-3">
                    <div class="cabinet-bl-filters__groups">
                        <div class="cabinet-bl-filter-group" data-bl-filter-group="presence">
                            <span class="cabinet-bl-filter-group__label">{{ __('Backlink filter presence') }}</span>
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ __('Backlink filter presence') }}">
                                <button type="button" class="btn btn-outline-secondary active" data-bl-filter="presence" data-bl-value="all">{{ __('Backlink filter all') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="presence" data-bl-value="found">{{ __('Backlink filter found') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="presence" data-bl-value="broken">{{ __('Backlink filter broken') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="presence" data-bl-value="unchecked">{{ __('Backlink filter unchecked') }}</button>
                            </div>
                        </div>
                        <div class="cabinet-bl-filter-group" data-bl-filter-group="nofollow">
                            <span class="cabinet-bl-filter-group__label">nofollow</span>
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="nofollow">
                                <button type="button" class="btn btn-outline-secondary active" data-bl-filter="nofollow" data-bl-value="all">{{ __('Backlink filter all') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="nofollow" data-bl-value="yes">{{ __('Backlink filter attr yes') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="nofollow" data-bl-value="no">{{ __('Backlink filter attr no') }}</button>
                            </div>
                        </div>
                        <div class="cabinet-bl-filter-group" data-bl-filter-group="noindex">
                            <span class="cabinet-bl-filter-group__label">noindex</span>
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="noindex">
                                <button type="button" class="btn btn-outline-secondary active" data-bl-filter="noindex" data-bl-value="all">{{ __('Backlink filter all') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="noindex" data-bl-value="yes">{{ __('Backlink filter attr yes') }}</button>
                                <button type="button" class="btn btn-outline-secondary" data-bl-filter="noindex" data-bl-value="no">{{ __('Backlink filter attr no') }}</button>
                            </div>
                        </div>
                    </div>
                    <div class="cabinet-bl-filters__meta mt-2 d-flex flex-wrap align-items-center gap-2">
                        <span class="small text-secondary" id="cabinet-bl-filter-count"></span>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none px-1 d-none" id="cabinet-bl-filter-reset">{{ __('Backlink filter reset') }}</button>
                    </div>
                </div>
            </div>

            <form action="{{ route('delete.links', $project->id) }}"
                  method="post"
                  id="cabinet-bl-bulk-form"
                  class="cabinet-bl-bulk-bar d-none"
                  onsubmit='return confirm(@json(__('Backlink bulk delete confirm')))'>
                @csrf
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small fw-semibold" id="cabinet-bl-bulk-count">0</span>
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Backlink bulk delete') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="cabinet-bl-bulk-clear">{{ __('Backlink bulk clear') }}</button>
                </div>
            </form>

            <div class="cabinet-bl-table-wrap">
                <table class="table table-sm cabinet-bl-table" id="cabinet-bl-links-table" aria-describedby="cabinet-bl-links-caption">
                    <caption id="cabinet-bl-links-caption" class="visually-hidden">{{ __('Backlink links in project') }}</caption>
                    <thead>
                    <tr>
                        <th class="cabinet-bl-col-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="cabinet-bl-select-all"
                                   aria-label="{{ __('Backlink select all visible') }}"
                                   title="{{ __('Backlink select all visible') }}">
                        </th>
                        <th class="cabinet-bl-col-wide">{{ __('Backlink col donor') }}</th>
                        <th class="cabinet-bl-col-wide">{{ __('Backlink col acceptor') }}</th>
                        <th>{{ __('Backlink col anchorless short') }}</th>
                        <th>{{ __('Backlink col anchor short') }}</th>
                        <th>{{ __('Backlink col nofollow short') }}</th>
                        <th>{{ __('Backlink col noindex short') }}</th>
                        <th>{{ __('Backlink col last check') }}</th>
                        <th>{{ __('Backlink col status') }}</th>
                        <th>{{ __('Backlink col actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($project->link as $link)
                        @php
                            $isAnchorless = \App\Support\BacklinkHtmlMatcher::isAnchorless(
                                (string) $link->anchor,
                                (string) $link->link
                            );
                            $filterMeta = \App\Services\Backlink\BacklinkChecker::statusFilterMeta(
                                (string) ($link->status ?? ''),
                                $link->broken ?? null
                            );
                        @endphp
                        <tr id="{{ $link->id }}"
                            class="cabinet-bl-link-row"
                            data-bl-presence="{{ $filterMeta['presence'] }}"
                            data-bl-nofollow="{{ $filterMeta['nofollow'] }}"
                            data-bl-noindex="{{ $filterMeta['noindex'] }}">
                            <td class="cabinet-bl-td-check">
                                <input type="checkbox"
                                       class="form-check-input cabinet-bl-row-check"
                                       name="ids[]"
                                       value="{{ $link->id }}"
                                       form="cabinet-bl-bulk-form"
                                       aria-label="{{ __('Backlink select link') }}">
                            </td>
                            <td>
                                {!! Form::textarea('site_donor', $link->site_donor, [
                                    'class' => 'form-control backlink cabinet-bl-cell-textarea',
                                    'rows' => 3,
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::textarea('link', $link->link, [
                                    'class' => 'form-control backlink cabinet-bl-cell-textarea cabinet-bl-link-url',
                                    'rows' => 3,
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::select('anchorless', ['1' => __('Yes'), '0' => __('No')], $isAnchorless ? '1' : '0', [
                                    'class' => 'form-select cabinet-bl-anchorless',
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::textarea('anchor', $isAnchorless ? '' : $link->anchor, [
                                    'class' => 'form-control backlink cabinet-bl-cell-textarea cabinet-bl-anchor-text',
                                    'rows' => 3,
                                    'placeholder' => __('Backlink anchor placeholder'),
                                    'disabled' => $isAnchorless,
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::select('nofollow', ['1' => __('Yes'), '0' => __('No')], $link->nofollow, [
                                    'class' => 'form-select backlink',
                                ]) !!}
                            </td>
                            <td>
                                {!! Form::select('noindex', ['1' => __('Yes'), '0' => __('No')], $link->noindex, [
                                    'class' => 'form-select backlink',
                                ]) !!}
                            </td>
                            <td class="text-nowrap small text-secondary">
                                @isset($link->last_check)
                                    {{ $link->last_check }}
                                @else
                                    <span class="text-muted">—</span>
                                @endisset
                            </td>
                            <td class="cabinet-bl-status-cell">
                                @include('backlink.partials.status-badges', ['status' => $link->status])
                            </td>
                            <td class="cabinet-bl-actions-cell">
                                <form action="{{ route('check.link', $link->id) }}" method="get" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary click_tracking mb-1"
                                            data-click="Hands scan"
                                            type="submit"
                                            title="{{ __('Backlink scan link') }}"
                                            aria-label="{{ __('Backlink scan link') }}">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <form action="{{ route('delete.link', $link->id) }}"
                                      method="post"
                                      class="d-inline"
                                      onsubmit='return confirm(@json(__('Backlink confirm delete link')))'>
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"
                                            type="submit"
                                            title="{{ __('Backlink delete link') }}"
                                            aria-label="{{ __('Backlink delete link') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="cabinet-bl-filter-empty text-center text-secondary small py-4 mb-0 d-none" id="cabinet-bl-filter-empty">
                    {{ __('Backlink filter empty') }}
                </p>
            </div>
        @endif
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                var $page = $('.cabinet-backlink-page');
                var oldValue = '';
                var oldProjectName = '';
                var $projectEl = $page.find('.cabinet-bl-project-name');

                function showToast(type) {
                    var selector = type === 'success' ? '.success-message' : '.error-message';
                    $page.find(selector).show();
                    setTimeout(function () {
                        $page.find(selector).hide(300);
                    }, 4000);
                }

                function updateProject(url, data) {
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        url: url,
                        data: $.extend(data, {_token: $('meta[name="csrf-token"]').attr('content')}),
                        success: function () {
                            showToast('success');
                        },
                        error: function () {
                            showToast('error');
                        }
                    });
                }

                $(document).ready(function () {
                    $projectEl.on('focus', function () {
                        oldProjectName = $(this).val();
                    });

                    $projectEl.on('blur', function () {
                        if (!$(this).val().length) {
                            showToast('error');
                            return false;
                        }

                        if (oldProjectName !== $(this).val()) {
                            updateProject("{{ route('edit.backlink') }}", {
                                id: $(this).data('project-id'),
                                name: $(this).attr('name'),
                                option: $(this).val(),
                            });
                        }
                    });

                    $page.find('.backlink').on('focus', function () {
                        oldValue = $(this).val();
                    });

                    $page.find('.backlink').on('blur', function () {
                        if ($(this).prop('disabled')) {
                            return;
                        }
                        if (oldValue !== $(this).val()) {
                            $.ajax({
                                type: 'POST',
                                dataType: 'json',
                                url: "{{ route('edit.link') }}",
                                data: {
                                    id: $(this).closest('tr').attr('id'),
                                    name: $(this).attr('name'),
                                    option: $(this).val(),
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function () {
                                    showToast('success');
                                },
                                error: function () {
                                    showToast('error');
                                }
                            });
                        }
                    });

                    function saveAnchor($row, value) {
                        $.ajax({
                            type: 'POST',
                            dataType: 'json',
                            url: "{{ route('edit.link') }}",
                            data: {
                                id: $row.attr('id'),
                                name: 'anchor',
                                option: value,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function () {
                                showToast('success');
                            },
                            error: function () {
                                showToast('error');
                            }
                        });
                    }

                    $page.on('change', '.cabinet-bl-anchorless', function () {
                        var $row = $(this).closest('tr');
                        var $anchor = $row.find('.cabinet-bl-anchor-text');
                        var isAnchorless = $(this).val() === '1';
                        if (isAnchorless) {
                            $anchor.val('').prop('disabled', true);
                            saveAnchor($row, '');
                        } else {
                            $anchor.prop('disabled', false).focus();
                        }
                    });

                    $page.find('.monitoring-options').select2({
                        allowClear: true,
                        selectOnClose: true,
                        placeholder: @json(__('Backlink monitoring placeholder')),
                        sorter: function (el) {
                            return el.sort(function (a, b) {
                                a = a.text.toLowerCase();
                                b = b.text.toLowerCase();
                                return a < b ? -1 : (a > b ? 1 : 0);
                            });
                        },
                    }).on('select2:select', function (e) {
                        var wrapper = e.target.closest('.cabinet-bl-monitoring-field');
                        updateProject("{{ route('edit.backlink') }}", {
                            id: wrapper.getAttribute('data-project-id'),
                            name: e.target.getAttribute('name'),
                            option: e.params.data.id,
                        });
                    }).on('select2:clear', function (e) {
                        var wrapper = e.target.closest('.cabinet-bl-monitoring-field');
                        updateProject("{{ route('edit.backlink') }}", {
                            id: wrapper.getAttribute('data-project-id'),
                            name: e.target.getAttribute('name'),
                            option: null,
                        });
                    });

                    var $progress = $('#cabinet-bl-check-progress');
                    var progressUrl = $progress.data('status-url');
                    var labels = {
                        progress: @json(__('Backlink check project progress', ['done' => ':done', 'total' => ':total'])),
                        finished: @json(__('Backlink check project finished', ['total' => ':total'])),
                        reload: @json(__('Backlink check project reload')),
                    };

                    function renderProgress(data) {
                        if (!data || !data.status || data.status === 'idle') {
                            return;
                        }
                        var total = parseInt(data.total, 10) || 0;
                        var done = parseInt(data.done, 10) || 0;
                        var pct = total > 0 ? Math.min(100, Math.round(100 * done / total)) : 0;
                        var busy = data.status === 'queued' || data.status === 'running';
                        $progress.removeClass('d-none');
                        var $bar = $('#cabinet-bl-check-progress-bar');
                        $bar.css('width', pct + '%').attr('aria-valuenow', pct);
                        $bar.toggleClass('progress-bar-striped progress-bar-animated', busy);
                        if (busy) {
                            $('#cabinet-bl-check-progress-label').text(
                                labels.progress.replace(':done', done).replace(':total', total)
                            );
                        } else if (data.status === 'done') {
                            $('#cabinet-bl-check-progress-label').text(
                                labels.finished.replace(':total', total)
                            );
                            if (!$progress.find('.cabinet-bl-reload-btn').length) {
                                $progress.find('.card-body > .d-flex').append(
                                    '<a href="' + window.location.href + '" class="btn btn-sm btn-outline-primary cabinet-bl-reload-btn">' +
                                    labels.reload + '</a>'
                                );
                            }
                        }
                    }

                    function pollProgress() {
                        if (!progressUrl) {
                            return;
                        }
                        $.getJSON(progressUrl).done(function (data) {
                            renderProgress(data);
                            if (data && (data.status === 'queued' || data.status === 'running')) {
                                setTimeout(pollProgress, 3000);
                            } else if (data && data.status === 'done') {
                                // один раз подсветили — пользователь обновит таблицу сам
                            }
                        });
                    }

                    if ($progress.data('busy') === 1 || $progress.data('busy') === '1') {
                        pollProgress();
                    }

                    var filters = { presence: 'all', nofollow: 'all', noindex: 'all' };
                    var $rows = $page.find('.cabinet-bl-link-row');
                    var $selectAll = $('#cabinet-bl-select-all');
                    var $bulkForm = $('#cabinet-bl-bulk-form');
                    var $bulkCount = $('#cabinet-bl-bulk-count');
                    var $filterCount = $('#cabinet-bl-filter-count');
                    var $filterEmpty = $('#cabinet-bl-filter-empty');
                    var $filterReset = $('#cabinet-bl-filter-reset');
                    var countTpl = @json(__('Backlink filter shown', ['shown' => ':shown', 'total' => ':total']));
                    var selectedTpl = @json(__('Backlink bulk selected', ['count' => ':count']));

                    function visibleRows() {
                        return $rows.filter(':visible');
                    }

                    function updateBulkBar() {
                        var selected = $page.find('.cabinet-bl-row-check:checked').length;
                        $bulkCount.text(selectedTpl.replace(':count', selected));
                        $bulkForm.toggleClass('d-none', selected === 0);
                        var $visibleChecks = visibleRows().find('.cabinet-bl-row-check');
                        var visTotal = $visibleChecks.length;
                        var visChecked = $visibleChecks.filter(':checked').length;
                        $selectAll.prop('checked', visTotal > 0 && visChecked === visTotal);
                        $selectAll.prop('indeterminate', visChecked > 0 && visChecked < visTotal);
                    }

                    function applyFilters() {
                        var shown = 0;
                        $rows.each(function () {
                            var $row = $(this);
                            var ok = true;
                            ['presence', 'nofollow', 'noindex'].forEach(function (key) {
                                var want = filters[key];
                                if (want !== 'all' && $row.attr('data-bl-' + key) !== want) {
                                    ok = false;
                                }
                            });
                            $row.toggle(ok);
                            if (!ok) {
                                $row.find('.cabinet-bl-row-check').prop('checked', false);
                            } else {
                                shown++;
                            }
                        });
                        $filterCount.text(countTpl.replace(':shown', shown).replace(':total', $rows.length));
                        var filtered = filters.presence !== 'all' || filters.nofollow !== 'all' || filters.noindex !== 'all';
                        $filterReset.toggleClass('d-none', !filtered);
                        $filterEmpty.toggleClass('d-none', shown > 0);
                        $page.find('#cabinet-bl-links-table').toggleClass('d-none', shown === 0);
                        updateBulkBar();
                    }

                    $page.on('click', '[data-bl-filter]', function () {
                        var $btn = $(this);
                        var key = $btn.data('bl-filter');
                        var value = String($btn.data('bl-value'));
                        filters[key] = value;
                        $btn.closest('.cabinet-bl-filter-group')
                            .find('[data-bl-filter="' + key + '"]')
                            .removeClass('active');
                        $btn.addClass('active');
                        applyFilters();
                    });

                    $filterReset.on('click', function () {
                        filters = { presence: 'all', nofollow: 'all', noindex: 'all' };
                        $page.find('[data-bl-filter]').removeClass('active');
                        $page.find('[data-bl-filter][data-bl-value="all"]').addClass('active');
                        applyFilters();
                    });

                    $page.on('click keydown', '[data-bl-quick-filter]', function (e) {
                        if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) {
                            return;
                        }
                        e.preventDefault();
                        var value = String($(this).data('bl-quick-filter'));
                        filters.presence = value;
                        $page.find('[data-bl-filter="presence"]').removeClass('active');
                        $page.find('[data-bl-filter="presence"][data-bl-value="' + value + '"]').addClass('active');
                        applyFilters();
                        var el = document.getElementById('cabinet-bl-filters');
                        if (el) {
                            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    });

                    $selectAll.on('change', function () {
                        var checked = $(this).prop('checked');
                        visibleRows().find('.cabinet-bl-row-check').prop('checked', checked);
                        updateBulkBar();
                    });

                    $page.on('change', '.cabinet-bl-row-check', function () {
                        updateBulkBar();
                    });

                    $('#cabinet-bl-bulk-clear').on('click', function () {
                        $page.find('.cabinet-bl-row-check').prop('checked', false);
                        updateBulkBar();
                    });

                    if ($rows.length) {
                        applyFilters();
                    }
                });
            })();
        </script>
    @endslot
@endcomponent
