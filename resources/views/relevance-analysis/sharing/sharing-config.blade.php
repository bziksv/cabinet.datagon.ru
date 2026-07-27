@component('component.card', ['title' => __('Share your projects')])
    @section('content')
        @slot('css')
            <link rel="stylesheet" type="text/css"
                  href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('plugins/keyword-generator/css/style.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('plugins/jqcloud/css/jqcloud.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('plugins/toastr/toastr.css') }}"/>
            <link rel="stylesheet" type="text/css" href="{{ asset('plugins/relevance-analysis/css/style.css') }}"/>
            <style>
                .RelevanceAnalysis {
                    background: oldlace;
                }
                #users-access_wrapper .dataTables_filter {
                    float: left;
                    text-align: left;
                    margin-bottom: 0.5rem;
                }
                #users-access_wrapper .dataTables_length {
                    float: right;
                    text-align: right;
                    margin-bottom: 0.5rem;
                }
                #users-access_wrapper > .row:first-child > [class*="col-"]:first-child {
                    text-align: left;
                }
                #users-access_wrapper > .row:first-child > [class*="col-"]:last-child {
                    text-align: right;
                }
                #users-access_wrapper .dataTables_filter label,
                #users-access_wrapper .dataTables_length label {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.35rem;
                    margin: 0;
                }
                #users-access_wrapper .dataTables_length label {
                    justify-content: flex-end;
                }
                #users-access .access-select {
                    width: 100%;
                    max-width: 16rem;
                }
            </style>
        @endslot

        <div id="toast-container" class="toast-top-right success-message" style="display:none;">
            <div class="toast toast-success" aria-live="polite">
                <div class="toast-message" id="toast-message"></div>
            </div>
        </div>

        <div id="toast-container" class="toast-top-right error-message" style="display:none;">
            <div class="toast toast-error" aria-live="polite">
                <div class="toast-message error-message" id="toast-message"></div>
            </div>
        </div>

        <div class="modal fade" id="ProjectModal" tabindex="-1" aria-labelledby="ProjectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="ProjectModalLabel">{{ "Проект $project->name" }}</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div>
                            <input type="hidden" id="projectId" name="projectId" value="{{ $project->id }}">
                        </div>
                        <div>
                            <label for="email">Почта пользователя которому вы хотите дать доступ</label>
                            <input type="email" class="form form-control" id="email" name="email">
                        </div>
                        <div>
                            <label for="access">Уровень доступа</label>
                            <select name="access" id="access" class="form form-control">
                                <option value="1">Только просмотр</option>
                                <option value="2">Просмотр и возможность запуска повторного анализа</option>
                            </select>
                            <p class="form-text mb-0 mt-1 js-access-limit-hint d-none">
                                {{ __('Relevance share reanalysis limits hint') }}
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="button" class="btn btn-primary" id="setAccess">{{ __('Save') }}</button>
                    </div>
                </div>
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
                    @if(!empty($admin))
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
                <div class="mb-3">
                    <a href="{{ route('sharing.view') }}" class="btn btn-outline-secondary btn-sm mb-2">
                        <i class="fa fa-arrow-left me-1" aria-hidden="true"></i>{{ __('Back to list') }}
                    </a>
                    <h4 class="mb-0">{{ __('Project') }} {{ $project->name }}</h4>
                </div>

                <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Public link without registration') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    {{ __('Relevance share public hint ttl') }}
                </p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text"
                           id="publicShareUrl"
                           class="form-control"
                           readonly
                           value="{{ isset($publicShare) ? $publicShare->publicUrl() : '' }}"
                           placeholder="{{ __('Create a public link to copy it here') }}">
                    <button type="button" class="btn btn-outline-secondary" id="copyPublicShareUrl"
                            data-ra-tip="{{ e(__('Relevance share copy public url tip')) }}"
                            @if(empty($publicShare)) disabled @endif>
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @php($shareTtlOptions = \App\Support\RelevancePublicShareTtl::labelsForUi())
                    <label class="visually-hidden" for="publicShareTtl">{{ __('Relevance share ttl label') }}</label>
                    <select id="publicShareTtl" class="form-select form-select-sm" style="width: auto; min-width: 8rem;"
                            aria-label="{{ __('Relevance share ttl label') }}">
                        @foreach($shareTtlOptions as $days => $label)
                            <option value="{{ $days }}"
                                @if((int) $days === (int) (isset($publicShare) ? $publicShare->ttlDaysFromRecord() : 30)) selected @endif>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" id="createPublicShare">
                        {{ isset($publicShare) ? __('Refresh public link') : __('Create public link') }}
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="revokePublicShare" @if(empty($publicShare)) disabled @endif>
                        {{ __('Revoke public link') }}
                    </button>
                    <span class="text-muted small" id="publicShareExpires">
                        @if(!empty($publicShare))
                            {{ $publicShare->expiresLabel() }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

                <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Пользователи имеющие доступ до проекта</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#ProjectModal">
                        Дать доступ к проекту
                    </button>
                </div>
                <table id="users-access" class="table table-bordered table-hover dtr-inline mb-3">
                    <thead>
                    <tr>
                        <th>Почтовый адрес</th>
                        <th class="col-3">Права</th>
                        <th class="col-3">Дата выдачи доступа к проекту</th>
                        <th class="col-3"></th>
                    </tr>
                    </thead>
                    <tbody id="accessProjects">
                    @foreach($access as $item)
                        <tr>
                            <td>
                                {{ $item->user->email }}
                                <br>
                                <span class="text-muted">
                                {{ $item->user->name }}
                                    {{ $item->user->last_name }}
                            </span>
                            </td>
                            <td>
                                <select name="access" class="form-select form-select-sm access-select"
                                        data-target="{{ $item->id }}">
                                    <option value="1" @if($item->access == 1) selected @endif>{{ __('Viewing only') }}</option>
                                    <option value="2" @if($item->access == 2) selected @endif>{{ __('Relevance share access reanalyse short') }}</option>
                                </select>
                            </td>
                            <td>
                                {{ $item->created_at }}
                            </td>
                            <td>
                                <button class="btn btn-secondary w-75 removeAccess" data-target="{{ $item->id }}">
                                    Убрать доступ до проекта
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
            </div>
        </div>
        @slot('js')
            <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
            <script src="{{ asset('js/cabinet-relevance-tooltips.js') }}?v={{ @filemtime(public_path('js/cabinet-relevance-tooltips.js')) ?: time() }}"></script>
            <script>
                $(document).ready(function () {
                    const dtLang = {
                        search: @json(__('Search') . ':'),
                        lengthMenu: @json(__('show') . ' _MENU_ ' . __('records')),
                        emptyTable: @json(__('No records')),
                        zeroRecords: @json(__('No records')),
                        info: @json(__('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')),
                        infoEmpty: @json(__('Showing') . ' ' . __('from') . ' 0 ' . __('to') . ' 0 ' . __('of') . ' 0 ' . __('entries')),
                        infoFiltered: @json(__('(filtered from _MAX_ total)')),
                        paginate: { first: '«', last: '»', next: '»', previous: '«' },
                    };
                    const dtDom = "<'row'<'col-sm-12 col-md-6'f><'col-sm-12 col-md-6'l>>" +
                        "<'row'<'col-sm-12'tr>>" +
                        "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>";
                    window.initUsersAccessTable = function () {
                        return $('#users-access').DataTable({
                            pageLength: 10,
                            order: [[2, 'desc']],
                            columnDefs: [{ orderable: false, targets: [1, 3] }],
                            dom: dtDom,
                            language: dtLang,
                        });
                    };

                    if (typeof window.initRelevanceActionTips === 'function') {
                        window.initRelevanceActionTips(document);
                    }
                    function syncAccessLimitHint() {
                        $('.js-access-limit-hint').toggleClass('d-none', $('#access').val() !== '2');
                    }
                    $('#access').on('change', syncAccessLimitHint);
                    $('#ProjectModal').on('show.bs.modal', syncAccessLimitHint);
                    syncAccessLimitHint();

                    window.initUsersAccessTable();

                    $('#copyPublicShareUrl').on('click', function () {
                        const input = document.getElementById('publicShareUrl');
                        if (!input.value) {
                            return;
                        }
                        input.select();
                        document.execCommand('copy');
                    });

                    $('#createPublicShare').on('click', function () {
                        $.ajax({
                            type: 'POST',
                            dataType: 'json',
                            url: "{{ route('relevance.public.share.create') }}",
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                project_id: $('#projectId').val(),
                                ttl_days: $('#publicShareTtl').val(),
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    $('#publicShareUrl').val(response.url);
                                    $('#publicShareExpires').text(response.expires_label || '');
                                    if (response.ttl_days !== undefined) {
                                        $('#publicShareTtl').val(String(response.ttl_days));
                                    }
                                    $('#copyPublicShareUrl, #revokePublicShare').prop('disabled', false);
                                    $('#createPublicShare').text('{{ __('Refresh public link') }}');
                                    $('.toast-top-right.success-message').show(300);
                                    $('.toast-message').html(response.message);
                                    setTimeout(function () {
                                        $('.toast-top-right.success-message').hide(300);
                                    }, 3000);
                                } else {
                                    $('.toast-top-right.error-message').show(300);
                                    $('.toast-message.error-message').html(response.message);
                                }
                            },
                        });
                    });

                    $('#revokePublicShare').on('click', function () {
                        $.ajax({
                            type: 'POST',
                            dataType: 'json',
                            url: "{{ route('relevance.public.share.revoke') }}",
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                project_id: $('#projectId').val(),
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    $('#publicShareUrl').val('');
                                    $('#publicShareExpires').text('');
                                    $('#copyPublicShareUrl, #revokePublicShare').prop('disabled', true);
                                    $('#createPublicShare').text('{{ __('Create public link') }}');
                                    $('.toast-top-right.success-message').show(300);
                                    $('.toast-message').html(response.message);
                                    setTimeout(function () {
                                        $('.toast-top-right.success-message').hide(300);
                                    }, 3000);
                                }
                            },
                        });
                    });

                    setInterval(() => {
                        refreshAllMethods()
                    }, 500)
                });

                function refreshAllMethods() {
                    $('#setAccess').unbind().on('click', function () {
                        if ($('#email').val() == '') {
                            return;
                        }
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: "{{ route('get.access.to.my.project') }}",
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                email: $('#email').val(),
                                project_id: $('#projectId').val(),
                                access: $('#access').val()
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    $('.toast-top-right.success-message').show(300)
                                    $('.toast-message').html(response.message)
                                    setTimeout(() => {
                                        $('.toast-top-right.success-message').hide(300)
                                    }, 3000)
                                } else if (response.code === 415) {
                                    $('.toast-top-right.error-message').show(300)
                                    $('.toast-message.error-message').html(response.message)
                                    setTimeout(() => {
                                        $('.toast-top-right.error-message').hide(300)
                                    }, 3000)
                                    return
                                }

                                if (!response.object || !response.user) {
                                    return
                                }

                                let options
                                const access = String(response.object.access || '')
                                const optViewOnly = @json(__('Viewing only'))
                                const optReanalyse = @json(__('Relevance share access reanalyse short'))
                                options =
                                    '<option value="1"' + (access === '1' ? ' selected' : '') + '>' + optViewOnly + '</option>' +
                                    '<option value="2"' + (access === '2' ? ' selected' : '') + '>' + optReanalyse + '</option>'

                                $("#users-access").dataTable().fnDestroy();
                                $('#accessProjects').append(
                                    "<tr>" +
                                    "   <td>" + response.user.email +
                                    "<br>" +
                                    "   <span class='text-muted'>" + response.user.name + " " + response.user.last_name + "</span>" +
                                    "   </td>" +
                                    "   <td>" +
                                    '<select name="access" class="form-select form-select-sm access-select" data-target="' + response.object.id + '">' +
                                    options +
                                    '</select>' +
                                    "   </td>" +
                                    "   <td>" + response.object.created_at + "</td>" +
                                    "   <td> " +
                                    "       <button class='btn btn-secondary w-75 removeAccess' data-target='" + response.object.id + "'>" +
                                    "       Убрать доступ до проекта" +
                                    '       </button> ' +
                                    '   </td>' +
                                    '</tr>'
                                )
                                window.initUsersAccessTable()
                            },
                            error: function (response) {
                            }
                        });
                    });

                    $('.removeAccess').unbind().on('click', function () {
                        let button = $(this)
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: "{{ route('remove.access.to.my.project') }}",
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                id: button.attr('data-target'),
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    $("#users-access").dataTable().fnDestroy();
                                    $('.toast-top-right.success-message').show(300)
                                    $('.toast-message').html(response.message)
                                    setTimeout(() => {
                                        $('.toast-top-right.success-message').hide(300)
                                    }, 3000)
                                    button.parent().parent().remove()
                                    window.initUsersAccessTable()
                                } else if (response.code === 415) {
                                    $('.toast-top-right.error-message').show(300)
                                    $('.toast-message.error-message').html(response.message)
                                    setTimeout(() => {
                                        $('.toast-top-right.error-message').hide(300)
                                    }, 3000)
                                }
                            },
                        });
                    });

                    $('.access-select').unbind().on('change', function () {
                        let elem = $(this)
                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: "{{ route('change.access.to.my.project') }}",
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                access: elem.val(),
                                id: elem.attr('data-target'),
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    $('.toast-top-right.success-message').show(300)
                                    $('.toast-message').html(response.message)
                                    setTimeout(() => {
                                        $('.toast-top-right.success-message').hide(300)
                                    }, 3000)
                                } else if (response.code === 415) {
                                    $('.toast-top-right.error-message').show(300)
                                    $('.toast-message.error-message').html(response.message)
                                    setTimeout(() => {
                                        $('.toast-top-right.error-message').hide(300)
                                    }, 3000)
                                }
                            },
                        });
                    })
                }
            </script>
        @endslot
    @endsection
@endcomponent
