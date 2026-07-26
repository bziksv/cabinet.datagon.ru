@php
    $allTags = collect($projects)->flatMap(function ($p) {
        return $p->relevanceTags;
    })->unique('id')->values();
@endphp
@component('component.card', ['title' => __('Share your projects')])
    @slot('css')
        <link rel="stylesheet" type="text/css"
              href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/toastr/toastr.css') }}"/>
        <link rel="stylesheet" type="text/css"
              href="{{ asset('plugins/relevance-analysis/css/style.css') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/css/style.css')) ?: time() }}"/>
        <style>
            #my-projects-table { width: 100% !important; }
            #my-projects-table th,
            #my-projects-table td { vertical-align: top; }
            .cabinet-share-chip {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                margin: 0 .35rem .35rem 0;
                padding: .2rem .55rem;
                border: 1px solid var(--bs-border-color);
                border-radius: 999px;
                background: #fff;
                font-size: .8125rem;
                max-width: 100%;
            }
            .cabinet-share-chip__email { font-weight: 600; }
            .cabinet-share-chip__level { color: var(--bs-secondary-color); }
            .cabinet-share-chip select {
                width: auto;
                min-width: 9.5rem;
                max-width: 12rem;
                padding: .15rem .4rem;
                font-size: .75rem;
                height: auto;
            }
            .cabinet-share-chip__remove {
                border: 0;
                background: transparent;
                color: var(--bs-secondary-color);
                padding: 0 .15rem;
                line-height: 1;
            }
            .cabinet-share-chip__remove:hover { color: #dc3545; }
            .cabinet-share-empty { color: var(--bs-secondary-color); font-size: .875rem; }
            .cabinet-share-project-list {
                max-height: 280px;
                overflow: auto;
                border: 1px solid var(--bs-border-color);
                border-radius: .375rem;
                padding: .5rem .75rem;
            }
            .cabinet-share-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
            .cabinet-share-tag {
                display: inline-block;
                margin: 0 .25rem .25rem 0;
                font-size: .8rem;
            }
        </style>
    @endslot

    <div class="toast-top-right success-message" style="display:none;">
        <div class="toast toast-success" aria-live="polite">
            <div class="toast-message" id="share-toast-ok"></div>
        </div>
    </div>
    <div class="toast-top-right error-message" style="display:none;">
        <div class="toast toast-error" aria-live="polite">
            <div class="toast-message" id="share-toast-err"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex p-0">
            <ul class="nav nav-pills p-2">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('relevance-analysis') }}">{{ __('Analyzer') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('create.queue.view') }}">{{ __('Create page analysis tasks') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('relevance.history') }}">{{ __('History') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('sharing.view') }}" class="nav-link active">{{ __('Share your projects') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('access.project') }}" class="nav-link">{{ __('Projects available to you') }}</a>
                </li>
                @if($admin)
                    <li class="nav-item">
                        <a class="nav-link admin-link" href="{{ route('all.relevance.projects') }}">{{ __('Statistics') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link admin-link" href="{{ route('show.config') }}">{{ __('Module administration') }}</a>
                    </li>
                @endif
            </ul>
        </div>

        <div class="card-body">
            <div class="cabinet-share-toolbar">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accessModal">
                    <i class="fa fa-user-plus me-1" aria-hidden="true"></i>{{ __('Give access') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#offAccessModal">
                    <i class="fa fa-user-times me-1" aria-hidden="true"></i>{{ __('Take access rights') }}
                </button>
            </div>

            <table id="my-projects-table" class="table table-bordered table-striped table-hover dataTable dtr-inline mb-0">
                <thead>
                <tr>
                    <th>{{ __('Project name') }}</th>
                    <th>{{ __('Tags') }}</th>
                    <th>{{ __('Users who have access to the project') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($projects as $item)
                    <tr id="story-id-{{ $item->id }}" data-project-id="{{ $item->id }}">
                        <td>
                            <span class="fw-semibold">{{ $item->name }}</span>
                        </td>
                        <td>
                            @forelse($item->relevanceTags as $tag)
                                <span class="cabinet-share-tag" style="color: {{ $tag->color }}">{{ $tag->name }}</span>
                            @empty
                                <span class="cabinet-share-empty">—</span>
                            @endforelse
                        </td>
                        <td class="sharing-users-cell" data-project-id="{{ $item->id }}">
                            @forelse($item->sharing as $share)
                                @include('relevance-analysis.sharing.partials.access-chip', ['share' => $share])
                            @empty
                                <span class="cabinet-share-empty js-share-empty">{{ __('No shared users yet') }}</span>
                            @endforelse
                        </td>
                        <td class="text-nowrap">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary js-grant-one"
                                    data-project-id="{{ $item->id }}"
                                    data-project-name="{{ $item->name }}">
                                + {{ __('Give access') }}
                            </button>
                            <a href="{{ route('share.project.conf', $item->id) }}" class="btn btn-sm btn-secondary">
                                {{ __('More') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Выдача: пользователь → проекты --}}
    <div class="modal fade" id="accessModal" tabindex="-1" aria-labelledby="accessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accessModalLabel">{{ __('Give access') }}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="access-email">{{ __('User email') }}</label>
                        <input type="email" class="form-control" id="access-email" autocomplete="off"
                               placeholder="user@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="access-level">{{ __('Access level') }}</label>
                        <select id="access-level" class="form-select">
                            <option value="1">{{ __('Viewing only') }}</option>
                            <option value="2">{{ __('Viewing and the ability to run a re-analysis') }}</option>
                        </select>
                    </div>
                    @if($allTags->isNotEmpty())
                        <div class="mb-3">
                            <label class="form-label" for="access-tag-filter">{{ __('Filter by tag') }}</label>
                            <select id="access-tag-filter" class="form-select">
                                <option value="">{{ __('All projects') }}</option>
                                @foreach($allTags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="form-label mb-0">{{ __('Projects') }}</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="access-select-all">{{ __('Select all') }}</button>
                            <button type="button" class="btn btn-outline-secondary" id="access-select-none">{{ __('Clear') }}</button>
                        </div>
                    </div>
                    <div class="cabinet-share-project-list" id="access-project-list">
                        @forelse($projects as $item)
                            <div class="form-check js-access-project-row"
                                 data-project-id="{{ $item->id }}"
                                 data-tags="{{ $item->relevanceTags->pluck('id')->implode(',') }}">
                                <input class="form-check-input js-access-project" type="checkbox"
                                       value="{{ $item->id }}" id="grant-p-{{ $item->id }}">
                                <label class="form-check-label" for="grant-p-{{ $item->id }}">{{ $item->name }}</label>
                            </div>
                        @empty
                            <p class="text-secondary mb-0">{{ __('No records') }}</p>
                        @endforelse
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary set-access-button">{{ __('Give access') }}</button>
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Отзыв: только уже выданные --}}
    <div class="modal fade" id="offAccessModal" tabindex="-1" aria-labelledby="offAccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="offAccessModalLabel">{{ __('Take access rights') }}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="off-email">{{ __('User email') }}</label>
                        <div class="input-group">
                            <input type="email" class="form-control" id="off-email" autocomplete="off"
                                   placeholder="user@example.com">
                            <button type="button" class="btn btn-outline-secondary" id="off-load-projects">
                                {{ __('Show projects') }}
                            </button>
                        </div>
                        <p class="form-text mb-0">{{ __('Only projects already shared with this user will be listed.') }}</p>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="form-label mb-0">{{ __('Projects with access') }}</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="off-select-all">{{ __('Select all') }}</button>
                            <button type="button" class="btn btn-outline-secondary" id="off-select-none">{{ __('Clear') }}</button>
                        </div>
                    </div>
                    <div class="cabinet-share-project-list" id="off-project-list">
                        <p class="text-secondary mb-0 js-off-placeholder">{{ __('Enter email and load the list.') }}</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger off-access-button">{{ __('Take access rights') }}</button>
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script>
            (function ($) {
                const i18n = {
                    viewOnly: @json(__('Viewing only')),
                    viewReanalyse: @json(__('Viewing and launching a re-analysis')),
                    emailRequired: @json(__('Enter user email')),
                    projectsRequired: @json(__('Select at least one project')),
                    noShared: @json(__('This user has no access to your projects')),
                    loadFailed: @json(__('An unexpected error has occurred, please contact the administrator')),
                    emptyUsers: @json(__('No shared users yet')),
                    removeConfirm: @json(__('Remove access') + '?'),
                };

                const routes = {
                    grant: @json(route('get.multiply.access.to.my.project')),
                    revokeMulti: @json(route('remove.multiply.access')),
                    revokeOne: @json(route('remove.access.to.my.project')),
                    change: @json(route('change.access.to.my.project')),
                    sharedForEmail: @json(route('sharing.projects.for.email')),
                };

                function csrf() {
                    return $('meta[name="csrf-token"]').attr('content');
                }

                function toastOk(msg) {
                    $('#share-toast-ok').html(msg);
                    $('.success-message').show(200);
                    setTimeout(function () { $('.success-message').hide(200); }, 3500);
                }

                function toastErr(msg) {
                    $('#share-toast-err').html(msg);
                    $('.error-message').show(200);
                    setTimeout(function () { $('.error-message').hide(200); }, 4500);
                }

                function escapeHtml(str) {
                    return $('<div>').text(str == null ? '' : String(str)).html();
                }

                function levelLabel(access) {
                    return String(access) === '2' ? i18n.viewReanalyse : i18n.viewOnly;
                }

                function chipHtml(share, user) {
                    const email = user.email || '';
                    const name = [user.name, user.last_name].filter(Boolean).join(' ').trim();
                    const access = String(share.access);
                    return (
                        '<span class="cabinet-share-chip" data-share-id="' + share.id + '" data-project-id="' + share.project_id + '">' +
                        '<span class="cabinet-share-chip__email" title="' + escapeHtml(name) + '">' + escapeHtml(email) + '</span>' +
                        '<select class="form-select form-select-sm access-select" data-share-id="' + share.id + '">' +
                        '<option value="1"' + (access === '1' ? ' selected' : '') + '>' + escapeHtml(i18n.viewOnly) + '</option>' +
                        '<option value="2"' + (access === '2' ? ' selected' : '') + '>' + escapeHtml(i18n.viewReanalyse) + '</option>' +
                        '</select>' +
                        '<button type="button" class="cabinet-share-chip__remove removeAccess" data-share-id="' + share.id + '" title="' + escapeHtml(i18n.removeConfirm) + '">&times;</button>' +
                        '</span>'
                    );
                }

                function ensureEmptyPlaceholder($cell) {
                    if (!$cell.find('.cabinet-share-chip').length && !$cell.find('.js-share-empty').length) {
                        $cell.append('<span class="cabinet-share-empty js-share-empty">' + escapeHtml(i18n.emptyUsers) + '</span>');
                    }
                }

                function addChips(objects, user) {
                    $.each(objects || [], function (_, share) {
                        const $cell = $('.sharing-users-cell[data-project-id="' + share.project_id + '"]');
                        $cell.find('.js-share-empty').remove();
                        if ($cell.find('.cabinet-share-chip[data-share-id="' + share.id + '"]').length) {
                            return;
                        }
                        $cell.append(chipHtml(share, user));
                    });
                }

                function removeChip(shareId) {
                    const $chip = $('.cabinet-share-chip[data-share-id="' + shareId + '"]');
                    const $cell = $chip.closest('.sharing-users-cell');
                    $chip.remove();
                    ensureEmptyPlaceholder($cell);
                }

                function selectedGrantIds() {
                    return $('.js-access-project:visible:checked').map(function () {
                        return $(this).val();
                    }).get();
                }

                function selectedRevokeIds() {
                    return $('.js-off-project:checked').map(function () {
                        return $(this).val();
                    }).get();
                }

                function filterGrantProjects() {
                    const tagId = String($('#access-tag-filter').val() || '');
                    $('.js-access-project-row').each(function () {
                        const tags = String($(this).data('tags') || '').split(',').filter(Boolean);
                        const show = !tagId || tags.indexOf(tagId) !== -1;
                        $(this).toggle(show);
                        if (!show) {
                            $(this).find('.js-access-project').prop('checked', false);
                        }
                    });
                }

                $(function () {
                    $('#my-projects-table').DataTable({
                        autoWidth: false,
                        pageLength: 10,
                        order: [[0, 'asc']],
                        columnDefs: [
                            { orderable: false, targets: [2, 3] },
                        ],
                        language: {
                            search: '{{ __('Search') }}:',
                            lengthMenu: '{{ __('show') }} _MENU_ {{ __('records') }}',
                            emptyTable: '{{ __('No records') }}',
                            info: '{{ __('Showing') }} {{ __('from') }} _START_ {{ __('to') }} _END_ {{ __('of') }} _TOTAL_ {{ __('entries') }}',
                            paginate: { first: '«', last: '»', next: '»', previous: '«' },
                        },
                    });

                    $('#access-tag-filter').on('change', filterGrantProjects);
                    $('#access-select-all').on('click', function () {
                        $('.js-access-project-row:visible .js-access-project').prop('checked', true);
                    });
                    $('#access-select-none').on('click', function () {
                        $('.js-access-project').prop('checked', false);
                    });
                    $('#off-select-all').on('click', function () {
                        $('.js-off-project').prop('checked', true);
                    });
                    $('#off-select-none').on('click', function () {
                        $('.js-off-project').prop('checked', false);
                    });

                    $('.js-grant-one').on('click', function () {
                        const id = String($(this).data('project-id'));
                        $('.js-access-project').prop('checked', false);
                        $('#grant-p-' + id).prop('checked', true);
                        $('#access-tag-filter').val('');
                        filterGrantProjects();
                        const el = document.getElementById('accessModal');
                        if (window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(el).show();
                        } else {
                            $(el).modal('show');
                        }
                    });

                    $('.set-access-button').on('click', function () {
                        const email = $.trim($('#access-email').val() || '');
                        const ids = selectedGrantIds();
                        if (!email) {
                            toastErr(i18n.emailRequired);
                            return;
                        }
                        if (!ids.length) {
                            toastErr(i18n.projectsRequired);
                            return;
                        }
                        const $btn = $(this).prop('disabled', true);
                        $.ajax({
                            type: 'POST',
                            url: routes.grant,
                            dataType: 'json',
                            data: {
                                _token: csrf(),
                                email: email,
                                access: $('#access-level').val(),
                                ids: ids,
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    toastOk(response.message);
                                    addChips(response.objects || [], response.user || {});
                                    if ((response.objects || []).length) {
                                        bootstrap.Modal.getInstance(document.getElementById('accessModal')).hide();
                                    }
                                } else {
                                    toastErr(response.message || i18n.loadFailed);
                                }
                            },
                            error: function () { toastErr(i18n.loadFailed); },
                            complete: function () { $btn.prop('disabled', false); },
                        });
                    });

                    $('#off-load-projects').on('click', function () {
                        const email = $.trim($('#off-email').val() || '');
                        if (!email) {
                            toastErr(i18n.emailRequired);
                            return;
                        }
                        const $btn = $(this).prop('disabled', true);
                        $.ajax({
                            type: 'GET',
                            url: routes.sharedForEmail,
                            dataType: 'json',
                            data: { email: email },
                            success: function (response) {
                                const $list = $('#off-project-list').empty();
                                if (response.code !== 200) {
                                    toastErr(response.message || i18n.loadFailed);
                                    $list.append('<p class="text-secondary mb-0">' + escapeHtml(response.message || i18n.loadFailed) + '</p>');
                                    return;
                                }
                                const projects = response.projects || [];
                                if (!projects.length) {
                                    $list.append('<p class="text-secondary mb-0">' + escapeHtml(i18n.noShared) + '</p>');
                                    return;
                                }
                                projects.forEach(function (p) {
                                    $list.append(
                                        '<div class="form-check">' +
                                        '<input class="form-check-input js-off-project" type="checkbox" value="' + p.id + '" id="off-p-' + p.id + '" checked>' +
                                        '<label class="form-check-label" for="off-p-' + p.id + '">' + escapeHtml(p.name) +
                                        ' <span class="text-secondary">(' + escapeHtml(levelLabel(p.access)) + ')</span></label>' +
                                        '</div>'
                                    );
                                });
                            },
                            error: function () { toastErr(i18n.loadFailed); },
                            complete: function () { $btn.prop('disabled', false); },
                        });
                    });

                    $('.off-access-button').on('click', function () {
                        const email = $.trim($('#off-email').val() || '');
                        const ids = selectedRevokeIds();
                        if (!email) {
                            toastErr(i18n.emailRequired);
                            return;
                        }
                        if (!ids.length) {
                            toastErr(i18n.projectsRequired);
                            return;
                        }
                        const $btn = $(this).prop('disabled', true);
                        $.ajax({
                            type: 'POST',
                            url: routes.revokeMulti,
                            dataType: 'json',
                            data: {
                                _token: csrf(),
                                email: email,
                                ids: ids,
                            },
                            success: function (response) {
                                if (response.success && response.code === 200) {
                                    toastOk(response.message);
                                    (response.objects || []).forEach(removeChip);
                                    bootstrap.Modal.getInstance(document.getElementById('offAccessModal')).hide();
                                    $('#off-project-list').html('<p class="text-secondary mb-0 js-off-placeholder">{{ __('Enter email and load the list.') }}</p>');
                                } else {
                                    toastErr(response.message || i18n.loadFailed);
                                }
                            },
                            error: function () { toastErr(i18n.loadFailed); },
                            complete: function () { $btn.prop('disabled', false); },
                        });
                    });

                    $(document).on('change', '.access-select', function () {
                        const $select = $(this);
                        const shareId = $select.data('share-id');
                        $.ajax({
                            type: 'POST',
                            url: routes.change,
                            dataType: 'json',
                            data: {
                                _token: csrf(),
                                id: shareId,
                                access: $select.val(),
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    toastOk(response.message);
                                } else {
                                    toastErr(response.message || i18n.loadFailed);
                                }
                            },
                            error: function () { toastErr(i18n.loadFailed); },
                        });
                    });

                    $(document).on('click', '.removeAccess', function () {
                        const shareId = $(this).data('share-id');
                        $.ajax({
                            type: 'POST',
                            url: routes.revokeOne,
                            dataType: 'json',
                            data: {
                                _token: csrf(),
                                id: shareId,
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    toastOk(response.message);
                                    removeChip(shareId);
                                } else {
                                    toastErr(response.message || i18n.loadFailed);
                                }
                            },
                            error: function () { toastErr(i18n.loadFailed); },
                        });
                    });
                });
            })(jQuery);
        </script>
    @endslot
@endcomponent
