@component('component.card', [
    'title' => 'Аудит сайта',
    'titleHtml' => e('Аудит сайта') . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-audit'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-module-registry.css') }}?v={{ @filemtime(public_path('css/cabinet-module-registry.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sa-page cabinet-sa-admin-page">
        @include('pages.partials.site-audit-module-nav', ['active' => 'admin'])

        @if(session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('pages.partials.site-audit-admin-capacity', [
            'capacity' => $capacity ?? [],
            'fields' => $fields ?? [],
        ])

        <div class="row g-3 align-items-start mb-3">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header py-2">
                        <h3 class="card-title h6 mb-0">{{ __('Module administration') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small mb-2">
                            Сводка по использованию модуля «Аудит сайта» и реестр проверок всех пользователей.
                            Отсюда можно сразу открыть чужой отчёт (только Super Admin / admin).
                        </p>
                        <ul class="small text-secondary mb-0 ps-3">
                            <li>Проектов всего: <strong>{{ number_format($stats['projects_total'] ?? 0, 0, '', ' ') }}</strong></li>
                            <li>Проверок всего: <strong>{{ number_format($stats['crawls_total'] ?? 0, 0, '', ' ') }}</strong></li>
                            <li>Активных пользователей за 7 дней: <strong>{{ number_format($stats['users_active_7d'] ?? 0, 0, '', ' ') }}</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-primary"><i class="bi bi-people" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Пользователи с проектами</span>
                        <span class="info-box-number">{{ number_format($stats['users_with_projects'] ?? 0, 0, '', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-success"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Готовых проверок</span>
                        <span class="info-box-number">{{ number_format($stats['crawls_done'] ?? 0, 0, '', ' ') }}</span>
                        <span class="info-box-text">В работе: {{ number_format($stats['crawls_running'] ?? 0, 0, '', ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('pages.partials.site-audit-admin-registry', ['registry' => $registry])
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/search.js') }}"></script>
        <script>
            $(document).ready(function () {
                var $table = $('#cabinet-sa-registry-table');
                if (!$table.length) return;
                $table.DataTable({
                    dom: '<"row align-items-center g-2 cabinet-sa-dt-controls"<"col-sm-auto"l><"col-sm-auto ms-auto"f>>rt<"row align-items-center g-2 cabinet-sa-dt-footer"<"col-sm-auto"i><"col-sm-auto ms-auto"p>>',
                    autoWidth: false,
                    pageLength: 25,
                    order: [[4, 'desc']],
                    language: {
                        paginate: { first: '«', last: '»', next: '»', previous: '«' },
                    },
                    oLanguage: {
                        sSearch: @json(__('Search') . ':'),
                        sLengthMenu: @json(__('show') . ' _MENU_ ' . __('records')),
                        sEmptyTable: @json(__('No records')),
                        sZeroRecords: @json('Совпадений не найдено'),
                        sInfo: @json(__('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')),
                        sInfoEmpty: @json('Показано 0 записей'),
                        sInfoFiltered: @json('(отфильтровано из _MAX_)'),
                    },
                });
            });
        </script>
    @endslot
@endcomponent
