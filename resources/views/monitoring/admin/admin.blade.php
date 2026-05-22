@component('component.card', ['title' => __('Monitoring position')])

    @slot('css')
        <!-- Toastr -->
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <!-- DataTables -->
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])

    @endslot

    <div class="row">
        <div class="col-6">
            @include('monitoring.admin._btn')

            {!! Form::open(['route' => ['monitoring.admin.settings.update']]) !!}
                @include('monitoring.admin.settings.global', ['settings' => $settings['global']])
            {!! Form::close() !!}
        </div>
    </div>

    @slot('js')
        <!-- Toastr -->
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <!-- Bootstrap 4 -->
        <!-- DataTables  & Plugins -->
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <!-- InputMask -->
        <script src="{{ asset('plugins/inputmask/jquery.inputmask.bundle.js') }}"></script>
        <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>

        <script>
            toastr.options = {
                "preventDuplicates": true,
                "timeOut": "5000"
            };

            $('.time').inputmask("hh:mm", {
                placeholder: moment().format('H:mm'),
            });

        </script>
    @endslot


@endcomponent
