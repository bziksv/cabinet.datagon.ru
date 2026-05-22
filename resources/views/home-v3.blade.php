@extends('layouts.app')

@section('title', __('Main page'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-home-v3.css') }}">
@endsection

@section('content')
    <div class="cabinet-home-v3-page">
        @include('home.partials.layout-switcher', ['activeVariant' => 3])

        @include('home-v3.partials.kpi-strip', ['summary' => $summary])

        <div class="card shadow-sm mb-3 cabinet-home-v3-search-card">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div>
                        <h2 class="h5 mb-1">{{ __('Tool hub') }}</h2>
                        <p class="text-secondary small mb-0">
                            {{ __('Hello') }}, <strong>{{ $summary['displayName'] }}</strong> —
                            @php $moduleCount = count($modules); @endphp
                            {{ $moduleCount }}
                            {{ $moduleCount === 1 ? __('module available') : __('modules available') }}
                        </p>
                    </div>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-primary text-white border-primary">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </span>
                    <input type="search"
                           class="form-control form-control-lg"
                           id="cabinet-home-v3-module-search"
                           placeholder="{{ __('Find a module') }}…"
                           autocomplete="off"
                           aria-label="{{ __('Find a module') }}">
                </div>
            </div>
        </div>

        @include('home-v3.partials.icon-grid', ['modules' => $modules])
        @include('home-v3.partials.action-chips')
    </div>
@endsection

@section('js')
    <script>
        (function () {
            document.querySelectorAll('.cabinet-home-v3-tile').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (typeof $ === 'undefined') {
                        return;
                    }
                    $.ajax({
                        type: 'post',
                        url: @json(route('click.tracking')),
                        data: {
                            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            button_text: 'open_module_v3',
                            url: location.href,
                            project_id: link.getAttribute('data-project-id'),
                        },
                    });
                });
            });

            var input = document.getElementById('cabinet-home-v3-module-search');
            if (!input) {
                return;
            }

            var tiles = document.querySelectorAll('[data-cabinet-v3-module-title]');
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                var visible = 0;
                tiles.forEach(function (col) {
                    var title = (col.getAttribute('data-cabinet-v3-module-title') || '').toLowerCase();
                    var match = q === '' || title.indexOf(q) !== -1;
                    col.classList.toggle('is-hidden', !match);
                    if (match) {
                        visible++;
                    }
                });
                var empty = document.getElementById('cabinet-home-v3-grid-empty');
                if (empty) {
                    empty.classList.toggle('d-none', visible > 0 || q === '');
                }
            });
        })();
    </script>
@endsection
