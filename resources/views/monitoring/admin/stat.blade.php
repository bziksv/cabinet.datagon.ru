@component('component.card', ['title' => __('Monitoring position')])

    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
        <!-- Toastr -->
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <!-- DataTables -->
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])

    @endslot

    <div class="row">
        <div class="col-6">
            @include('monitoring.admin._btn')

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Удаление очередей</h3>
                </div>

                {!! Form::open(['route' => 'monitoring.stat.deleteQueues']) !!}
                <div class="card-body">

                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="delete_queues" class="custom-control-input" id="deleteQueues">
                                    <label class="custom-control-label" for="deleteQueues">Удалить всю очередь этого модуля</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label('user', __('User')) !!}
                                {!! Form::select('user', [], null, [
                                    'class' => 'form-select',
                                    'id' => 'stat-delete-user',
                                    'data-placeholder' => 'Email (мин. 2 символа)',
                                ]) !!}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                {!! Form::label('project', __('Project')) !!}
                                {!! Form::select('project', $sites, null, ['class' => 'form-select', 'placeholder' => 'Выберите проект']) !!}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-danger btn-sm">Удалить</button>
                </div>

                {!! Form::close() !!}
            </div>
        </div>

        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Общая статистика модуля</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                            @foreach($statistics as $statistic)
                            <tr>
                                <td style="width: 80%">{{ $statistic['name'] }}</td>
                                <td style="width: 20%"><span class="text-bold">{{ $statistic['val'] }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <table class="table table-bordered" id="queues" style="width:100%"></table>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
        <!-- Toastr -->
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <!-- Bootstrap 4 -->
        <!-- DataTables  & Plugins -->
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])

        <script>
            toastr.options = {
                "preventDuplicates": true,
                "timeOut": "5000"
            };

            $('#stat-delete-user').select2({
                width: '100%',
                placeholder: $('#stat-delete-user').data('placeholder') || '',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route('users.search-emails') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (data) {
                        return { results: data.results || [] };
                    },
                    cache: true
                }
            });

            let table = $('#queues').DataTable({
                dom: '<"card-header"<"card-title"><"float-right"l>><"card-body p-0"rt><"card-footer clearfix"p><"clear">',
                "ordering": false,
                lengthMenu: [30, 50, 100],
                pageLength: 50,
                pagingType: "simple_numbers",
                language: {
                    lengthMenu: "_MENU_",
                    search: "_INPUT_",
                    searchPlaceholder: "Search...",
                    paginate: {
                        "first":      "«",
                        "last":       "»",
                        "next":       "»",
                        "previous":   "«"
                    },
                    processing: '<img src="/img/1485.gif" style="width: 50px; height: 50px;">',
                },
                processing: true,
                serverSide: true,
                ajax: {
                    url: '/monitoring/stat',
                    type: 'POST',
                },
                order: [
                    [1, 'asc'],
                ],
                columns: [
                    {
                        title: 'ID',
                        data: 'id'
                    },
                    {
                        title: 'Пользователь',
                        data: 'user'
                    },
                    {
                        title: 'Email',
                        data: 'email'
                    },
                    {
                        title: 'Сайт',
                        data: 'site'
                    },
                    {
                        title: 'Группа',
                        data: 'group'
                    },
                    {
                        title: 'Параметры региона',
                        data: 'params'
                    },
                    {
                        title: 'Запрос',
                        data: 'query'
                    },
                    {
                        title: 'Приоритет',
                        data: 'priority'
                    },
                    {
                        title: 'Дата',
                        data: 'created_at'
                    },
                    {
                        title: 'Попыток',
                        data: 'attempts'
                    },
                ],
                initComplete: function () {
                    let api = this.api();
                    let json = api.ajax.json();

                    this.closest('.card').find('.card-header .card-title').html("Просмотр очереди.");
                    this.closest('.card').find('.card-header label').css('margin-bottom', 0);
                },
                drawCallback: function(){
                    $('.pagination').addClass('pagination-sm');
                },
            });

        </script>
    @endslot


@endcomponent
