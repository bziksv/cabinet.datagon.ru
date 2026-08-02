<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Enter PIN') }}</title>
    <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
</head>
<body class="cabinet-sr-public">
    <div class="cabinet-sr-pin">
        <h1>{{ __('Protected report') }}</h1>
        <p>{{ __('Enter PIN to open the SEO report') }}</p>
        @if(!empty($error))
            <div class="cabinet-sr-pin__error">{{ $error }}</div>
        @endif
        <form method="post" action="{{ route('seo-reports.public.unlock', ['token' => $token]) }}">
            @csrf
            <input type="password" name="pin" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            <button type="submit">{{ __('Open') }}</button>
        </form>
    </div>
</body>
</html>
