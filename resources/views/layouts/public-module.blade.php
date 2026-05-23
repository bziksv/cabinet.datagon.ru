<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    <title>@yield('title') — {{ \App\Support\TextAnalyzerPdfBranding::BRAND_NAME }}</title>
    @include('layouts.partials.lte4-head')
    <link rel="stylesheet" href="{{ asset('css/cabinet-public-module.css') }}">
    @yield('css')
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>
<body class="bg-body-tertiary cabinet-public-module-page">
<header class="cabinet-public-module-header">
    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <a href="{{ \App\Support\TextAnalyzerPdfBranding::BRAND_SITE }}" class="cabinet-public-module-brand text-decoration-none d-inline-flex align-items-center gap-2">
                <img src="{{ asset('img/logo.svg') }}" alt="{{ \App\Support\TextAnalyzerPdfBranding::BRAND_NAME }}" height="36">
            </a>
            <a href="{{ \App\Support\TextAnalyzerPdfBranding::BRAND_SITE }}" class="btn btn-sm btn-outline-primary">
                {{ __('Go to') }} {{ \App\Support\TextAnalyzerPdfBranding::BRAND_NAME }}
            </a>
        </div>
    </div>
</header>
<main class="container-fluid py-3 pb-5">
    @yield('content')
</main>
<footer class="cabinet-public-module-footer text-center small text-secondary py-3">
    &copy; {{ date('Y') }}
    <a href="{{ \App\Support\TextAnalyzerPdfBranding::BRAND_SITE }}" class="text-decoration-none">{{ \App\Support\TextAnalyzerPdfBranding::BRAND_NAME }}</a>
</footer>
@include('layouts.partials.lte4-scripts')
@yield('js')
</body>
</html>
