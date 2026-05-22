@extends('layouts.app')

@section('title', __('Main page'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-home.css') }}">
@endsection

@section('content')
    <div class="cabinet-home-page">
        @include('home.partials.layout-switcher', ['activeVariant' => 1])
        @include('home.partials.hero', ['summary' => $summary])
        @include('home.partials.stats', ['summary' => $summary])
        @include('home.partials.modules', ['modules' => $modules])
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var input = document.getElementById('cabinet-home-module-search');
            if (!input) {
                return;
            }
            var cards = document.querySelectorAll('[data-cabinet-module-title]');
            input.addEventListener('input', function () {
                var q = input.value.trim().toLowerCase();
                var visible = 0;
                cards.forEach(function (col) {
                    var title = (col.getAttribute('data-cabinet-module-title') || '').toLowerCase();
                    var match = q === '' || title.indexOf(q) !== -1;
                    col.classList.toggle('is-hidden', !match);
                    if (match) {
                        visible++;
                    }
                });
                var empty = document.getElementById('cabinet-home-modules-empty');
                if (empty) {
                    empty.classList.toggle('d-none', visible > 0 || q === '');
                }
            });

            document.querySelectorAll('.cabinet-home-module-open').forEach(function (link) {
                link.addEventListener('click', function () {
                    var card = link.closest('[data-project-id]');
                    if (!card || typeof $ === 'undefined') {
                        return;
                    }
                    $.ajax({
                        type: 'post',
                        url: @json(route('click.tracking')),
                        data: {
                            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            button_text: link.getAttribute('data-track') || 'open',
                            url: location.href,
                            project_id: card.getAttribute('data-project-id'),
                        },
                    });
                });
            });
        })();
    </script>
@endsection
