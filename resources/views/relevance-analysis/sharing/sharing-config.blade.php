@component('component.card', ['title' =>  "$project->name" ])
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
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="button" class="btn btn-primary" id="setAccess">{{ __('Save') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Public link without registration') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    {{ __('Anyone with the link can view the project for 30 days without logging in. View only.') }}
                </p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text"
                           id="publicShareUrl"
                           class="form-control"
                           readonly
                           value="{{ isset($publicShare) ? $publicShare->publicUrl() : '' }}"
                           placeholder="{{ __('Create a public link to copy it here') }}">
                    <button type="button" class="btn btn-outline-secondary" id="copyPublicShareUrl" @if(empty($publicShare)) disabled @endif>
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="createPublicShare">
                        {{ isset($publicShare) ? __('Refresh public link') : __('Create public link') }}
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="revokePublicShare" @if(empty($publicShare)) disabled @endif>
                        {{ __('Revoke public link') }}
                    </button>
                    <span class="text-muted small" id="publicShareExpires">
                        @if(!empty($publicShare))
                            {{ __('Valid until') }}: {{ $publicShare->expires_at->format('d.m.Y H:i') }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex p-0">
                <h3 class="p-3">{{ "Проект $project->name" }}</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5>Пользователи имеющие доступ до проекта</h5>
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
                                <select name="access" class="form form-control access-select" style="width: 350px"
                                        data-target="{{ $item->id }}">
                                    @if($item->access == 1)
                                        <option value="1">Только просмотр</option>
                                        <option value="2">Просмотр и запуск повторного анализа</option>
                                    @elseif($item->access == 2)
                                        <option value="2">Просмотр и запуск повторного анализа</option>
                                        <option value="1">Только просмотр</option>
                                    @endif
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
        @slot('js')
            <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
            <script>
                $(document).ready(function () {
                    $('#users-access').DataTable();

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
                            },
                            success: function (response) {
                                if (response.code === 201) {
                                    $('#publicShareUrl').val(response.url);
                                    $('#publicShareExpires').text('{{ __('Valid until') }}: ' + response.expires_at);
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
                                }

                                let options

                                if (response.object.access === '1') {
                                    options =
                                        '<option value="1">Только просмотр</option> ' +
                                        '<option value="2">Просмотр и  запуск повторного анализа</option>'
                                } else {
                                    options =
                                        '<option value="2">Просмотр и  запуск повторного анализа</option>' +
                                        '<option value="1">Только просмотр</option> '
                                }

                                $("#users-access").dataTable().fnDestroy();
                                $('#accessProjects').append(
                                    "<tr>" +
                                    "   <td>" + response.user.email +
                                    "<br>" +
                                    "   <span class='text-muted'>" + response.user.name + " " + response.user.last_name + "</span>" +
                                    "   </td>" +
                                    "   <td>" +
                                    '<select name="access" class="form form-control access-select" style="width: 350px" data-target="' + response.object.id + '">' +
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
                                $('#users-access').DataTable()
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
                                    $('#users-access').DataTable()
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
                        let elem = $('.access-select')
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
