<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    @include('layouts.partials.document-title')
    @include('layouts.partials.lte4-head')
    @if(config('app.env') !== 'local')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/cabinet-auth.css') }}">
    @yield('css')
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>
<body class="login-page bg-body-secondary">
@yield('content')

@if(config('app.env') !== 'local')
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
@endif
@include('layouts.partials.lte4-scripts')
<script src="{{ asset('js/cabinet-jquery-modal-bridge.js') }}"></script>
<script src="{{ asset('js/cabinet-bs5-shim.js') }}"></script>
<script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
@yield('js')

@if(config('app.env') !== 'local')
<script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () {(m[i].a = m[i].a || []).push(arguments);};
        m[i].l = 1 * new Date();
        k = e.createElement(t), a = e.getElementsByTagName(t)[0];
        k.async = 1;
        k.src = r;
        a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
    ym(89500732, 'init', {clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/89500732" style="position:absolute;left:-9999px;" alt=""/></div></noscript>
@endif
</body>
</html>
