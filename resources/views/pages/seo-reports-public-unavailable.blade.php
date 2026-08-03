<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('Report unavailable') }}</title>
    <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
</head>
<body class="cabinet-sr-public">
    <div class="cabinet-sr-pin">
        <h1>{{ $title ?? __('Report unavailable') }}</h1>
        <p>{{ $message ?? __('This public link has expired or does not exist.') }}</p>
        @if(!empty($hint))
            <p class="cabinet-sr-pin__hint">{{ $hint }}</p>
        @endif
    </div>
</body>
</html>
