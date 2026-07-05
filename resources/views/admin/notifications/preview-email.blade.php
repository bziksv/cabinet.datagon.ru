<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Users notify email preview title') }}</title>
    <style>
        body { margin: 0; padding: 1rem; background: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .preview-toolbar { margin-bottom: 1rem; padding: 0.75rem 1rem; background: #fff; border: 1px solid #dee2e6; border-radius: 0.375rem; font-size: 0.875rem; }
        .preview-frame { background: #fff; border: 1px solid #dee2e6; border-radius: 0.375rem; overflow: auto; }
    </style>
</head>
<body>
    <div class="preview-toolbar">
        <strong>{{ __('Users notify email preview title') }}</strong>
        · <code>{{ $eventId }}</code>
        · {{ __('Users notify email preview hint') }}
    </div>
    <div class="preview-frame">
        {!! $html !!}
    </div>
</body>
</html>
