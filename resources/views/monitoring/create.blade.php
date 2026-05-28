@component('component.card', ['title' => __('Monitoring v2 create wizard title')])

    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/bs-stepper/css/bs-stepper.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-create.css') }}?v={{ config('cabinet-monitoring.version') }}">
    @endslot

    <div class="cabinet-mon-create">
        <div class="cabinet-mon-create__head mb-3">
            <p class="text-secondary small mb-2">
                <a href="{{ route('monitoring.v2') }}">{{ __('Monitoring v2') }}</a>
                <span class="mx-1">/</span>
                <span>{{ __('Monitoring v2 create wizard title') }}</span>
            </p>
            <p class="cabinet-mon-create__lead mb-0">{{ __('Monitoring v2 create wizard lead') }}</p>
        </div>

        <div id="cabinet-mon-create-status" class="alert alert-info mb-3" role="status">
            {{ __('Monitoring v2 create status new') }}
        </div>

        <div class="bs-stepper cabinet-mon-create__stepper">
            <div class="bs-stepper-header" role="tablist">
                @include('monitoring.partials.stepper._titles', ['item' => '1', 'target' => 'project', 'name' => __('Monitoring v2 create step project')])
                <div class="line"></div>
                @include('monitoring.partials.stepper._titles', ['item' => '2', 'target' => 'keywords', 'name' => __('Monitoring v2 create step keywords')])
                <div class="line"></div>
                @include('monitoring.partials.stepper._titles', ['item' => '3', 'target' => 'competitors', 'name' => __('Monitoring v2 create step competitors')])
                <div class="line"></div>
                @include('monitoring.partials.stepper._titles', ['item' => '4', 'target' => 'regions', 'name' => __('Monitoring v2 create step regions')])
                <div class="line"></div>
                @include('monitoring.partials.stepper._titles', ['item' => '5', 'target' => 'scan', 'name' => __('Monitoring v2 create step scan')])
                <div class="line"></div>
                @include('monitoring.partials.stepper._titles', ['item' => '6', 'target' => 'save', 'name' => __('Monitoring v2 create step done')])
            </div>
            <div class="bs-stepper-content">
                <form class="needs-validation" method="post" action="{{ route('monitoring.store') }}" novalidate>
                    @csrf
                    @include('monitoring.partials.stepper._content', ['target' => 'project', 'buttons' => ['next', 'back']])
                    @include('monitoring.partials.stepper._content', ['target' => 'keywords', 'buttons' => ['previous', 'next']])
                    @include('monitoring.partials.stepper._content', ['target' => 'competitors', 'buttons' => ['previous', 'next']])
                    @include('monitoring.partials.stepper._content', ['target' => 'regions', 'buttons' => ['previous', 'next']])
                    @include('monitoring.partials.stepper._content', ['target' => 'scan', 'buttons' => ['previous', 'next']])
                    @include('monitoring.partials.stepper._content', ['target' => 'save', 'buttons' => ['previous', 'action']])
                </form>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/bs-stepper/js/bs-stepper.min.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('plugins/papaparse/papaparse.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables-editor/js/datatables_editor.min.js') }}"></script>
        <script src="{{ asset('plugins/inputmask/jquery.inputmask.bundle.js') }}"></script>
        <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
        <script>
            window.cabinetMonCreateConfig = {
                urls: {
                    create: @json(url('/monitoring/creator/create')),
                    update: @json(url('/monitoring/creator/update')),
                    edit: @json(url('/monitoring/creator/edit')),
                    queries: @json(url('/monitoring/creator/queries')),
                    competitors: @json(url('/monitoring/creator/competitors')),
                    regions: @json(url('/monitoring/creator/regions')),
                    groups: @json(url('/monitoring/creator/groups')),
                    location: @json(url('/api/location')),
                },
                toastr: { preventDuplicates: true, timeOut: 4000 },
                dtLang: {
                    lengthMenu: '_MENU_',
                    search: '_INPUT_',
                    searchPlaceholder: @json(__('Search...')),
                    paginate: { first: '«', last: '»', next: '»', previous: '«' },
                },
                i18n: {
                    statusNew: @json(__('Monitoring v2 create status new')),
                    statusSaved: @json(__('Monitoring v2 create status saved')),
                    statusProject: @json(__('Monitoring v2 create status project')),
                    errName: @json(__('Monitoring v2 create err name')),
                    errUrl: @json(__('Monitoring v2 create err url')),
                    errUrlFormat: @json(__('Monitoring v2 create err url format')),
                    errDomain: @json(__('Monitoring v2 create err domain')),
                    errCsv: @json(__('Monitoring v2 create err csv')),
                    errKeywords: @json(__('Monitoring v2 create err keywords')),
                    saved: @json(__('Monitoring v2 create saved')),
                    saveError: @json(__('Monitoring v2 create save error')),
                    needProject: @json(__('Monitoring v2 create need project')),
                    needTable: @json(__('Monitoring v2 create need table')),
                    needKeywords: @json(__('Monitoring v2 create need keywords')),
                    needRegions: @json(__('Monitoring v2 create need regions')),
                    added: @json(__('Monitoring v2 create added')),
                    groupAdded: @json(__('Monitoring v2 create group added')),
                    colQuery: @json(__('Query')),
                    colPage: @json(__('Relevant page')),
                    colGroup: @json(__('Group')),
                    colTarget: @json(__('Target')),
                    queryList: @json(__('Monitoring v2 create query list')),
                    mainGroup: @json(__('Main')),
                    regionPlaceholder: @json(__('Monitoring v2 create region placeholder')),
                    regionType: @json(__('Monitoring v2 create region type')),
                    deleteTitle: @json(__('Delete')),
                    deleteMsg: @json(__('Monitoring v2 create delete confirm')),
                    deleteBtn: @json(__('Delete')),
                    rangesHint: @json(__('Monitoring v2 create ranges hint')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-create.js') }}?v={{ config('cabinet-monitoring.version') }}"></script>
    @endslot

@endcomponent
