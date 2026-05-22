@extends('layouts.app')

@section('title', __('Main page'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-home-v2.css') }}">
@endsection

@section('content')
    <div class="cabinet-home-v2-page">
        @include('home.partials.layout-switcher', ['activeVariant' => 2])

        <div class="row g-3">
            <div class="col-lg-4 cabinet-home-v2-sidebar">
                @include('home-v2.partials.sidebar', ['summary' => $summary])
            </div>

            <div class="col-lg-8">
                @include('home-v2.partials.featured', ['featuredModules' => $featuredModules])
                @include('home-v2.partials.module-list', ['listModules' => $listModules, 'modules' => $modules])
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            function trackOpen(link) {
                var row = link.closest('[data-project-id]');
                if (!row || typeof $ === 'undefined') {
                    return;
                }
                $.ajax({
                    type: 'post',
                    url: @json(route('click.tracking')),
                    data: {
                        _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        button_text: 'open_module_v2',
                        url: location.href,
                        project_id: row.getAttribute('data-project-id'),
                    },
                });
            }

            document.querySelectorAll('.cabinet-home-v2-open').forEach(function (link) {
                link.addEventListener('click', function () {
                    trackOpen(link);
                });
            });

            var input = document.getElementById('cabinet-home-v2-module-search');
            if (!input) {
                return;
            }

            var items = document.querySelectorAll('[data-cabinet-v2-module-title]');
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                var visible = 0;
                items.forEach(function (el) {
                    var title = (el.getAttribute('data-cabinet-v2-module-title') || '').toLowerCase();
                    var match = q === '' || title.indexOf(q) !== -1;
                    el.classList.toggle('is-hidden', !match);
                    if (match) {
                        visible++;
                    }
                });
                var empty = document.getElementById('cabinet-home-v2-list-empty');
                if (empty) {
                    empty.classList.toggle('d-none', visible > 0 || q === '');
                }
            });
        })();
    </script>
@endsection
