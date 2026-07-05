@extends('layouts.app')

@section('title', __('Monitoring stat page title'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-admin.css')) ?: time() }}">
    @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])
@endsection

@section('content')
    <div class="cabinet-mon-admin-page cabinet-mon-stat-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-list-task text-primary" aria-hidden="true"></i>
                    <span>{{ __('Monitoring stat page title') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 42rem;">
                    {{ __('Monitoring stat page lead') }}
                </p>
            </div>
        </div>

        @include('monitoring.admin.partials.nav', ['active' => 'stat'])

        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <section class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="h6 mb-1">{{ __('Monitoring stat delete queues title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring stat delete queues lead') }}</p>
                    </div>
                    {!! Form::open(['route' => 'monitoring.stat.deleteQueues']) !!}
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="delete_queues" class="form-check-input" id="deleteQueues">
                            <label class="form-check-label" for="deleteQueues">{{ __('Monitoring stat delete all queues') }}</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="stat-delete-user">{{ __('User') }}</label>
                                {!! Form::select('user', [], null, [
                                    'class' => 'form-select form-select-sm',
                                    'id' => 'stat-delete-user',
                                    'data-placeholder' => __('Monitoring stat email placeholder'),
                                ]) !!}
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold" for="stat-delete-project">{{ __('Project') }}</label>
                                {!! Form::select('project', $sites, null, [
                                    'class' => 'form-select form-select-sm',
                                    'id' => 'stat-delete-project',
                                    'placeholder' => __('Monitoring stat project placeholder'),
                                ]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Delete') }}
                        </button>
                    </div>
                    {!! Form::close() !!}
                </section>
            </div>
            <div class="col-lg-6">
                <section class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h3 class="h6 mb-1">{{ __('Monitoring stat module stats title') }}</h3>
                        <p class="small text-secondary mb-0">{{ __('Monitoring stat module stats lead') }}</p>
                    </div>
                    <div class="card-body p-0">
                        @if(count($statistics))
                            <table class="table table-sm mb-0">
                                <tbody>
                                @foreach($statistics as $statistic)
                                    <tr>
                                        <td class="ps-3">{{ $statistic['name'] }}</td>
                                        <td class="text-end pe-3 fw-semibold">{{ $statistic['val'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <p class="text-secondary small px-3 py-3 mb-0">{{ __('Monitoring stat module stats empty') }}</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <section class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h3 class="h6 mb-0">{{ __('Monitoring stat queue title') }}</h3>
                    <p class="small text-secondary mb-0">{{ __('Monitoring stat queue lead') }}</p>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="queues" style="width:100%"></table>
                </div>
            </div>
        </section>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
    <script>
        toastr.options = {preventDuplicates: true, timeOut: 5000};

        $('#stat-delete-user').select2({
            width: '100%',
            placeholder: $('#stat-delete-user').data('placeholder') || '',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: @json(route('users.search-emails')),
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

        var statI18n = {
            searchPlaceholder: @json(__('Search...')),
            processing: @json(__('Monitoring stat queue processing')),
            cols: {
                id: @json(__('Monitoring stat col id')),
                user: @json(__('User')),
                email: @json(__('Email')),
                site: @json(__('Monitoring stat col site')),
                group: @json(__('Monitoring stat col group')),
                params: @json(__('Monitoring stat col region params')),
                query: @json(__('Monitoring stat col query')),
                priority: @json(__('Monitoring stat col priority')),
                created: @json(__('Monitoring stat col created')),
                attempts: @json(__('Monitoring stat col attempts')),
            }
        };

        $('#queues').DataTable({
            dom: '<"d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 border-bottom"lf>rt<"d-flex justify-content-between align-items-center p-2 border-top"ip>',
            ordering: false,
            lengthMenu: [30, 50, 100],
            pageLength: 50,
            pagingType: 'simple_numbers',
            language: {
                lengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: statI18n.searchPlaceholder,
                emptyTable: @json(__('Monitoring stat queue empty')),
                info: @json(__('Monitoring stat queue info')),
                infoEmpty: @json(__('Monitoring stat queue info empty')),
                paginate: {
                    first: '«',
                    last: '»',
                    next: '›',
                    previous: '‹'
                },
                processing: statI18n.processing,
            },
            processing: true,
            serverSide: true,
            ajax: {
                url: @json(route('monitoring.stat')),
                type: 'POST',
            },
            columns: [
                {title: statI18n.cols.id, data: 'id'},
                {title: statI18n.cols.user, data: 'user'},
                {title: statI18n.cols.email, data: 'email'},
                {title: statI18n.cols.site, data: 'site'},
                {title: statI18n.cols.group, data: 'group'},
                {title: statI18n.cols.params, data: 'params'},
                {title: statI18n.cols.query, data: 'query'},
                {title: statI18n.cols.priority, data: 'priority'},
                {title: statI18n.cols.created, data: 'created_at'},
                {title: statI18n.cols.attempts, data: 'attempts'},
            ],
            drawCallback: function () {
                $('.pagination').addClass('pagination-sm mb-0');
            },
        });
    </script>
@endsection
