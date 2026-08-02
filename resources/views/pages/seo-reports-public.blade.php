<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $snapshot['cover']['title'] ?? $project->domain }}</title>
    <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
</head>
<body class="cabinet-sr-public {{ !empty($presentation) ? 'cabinet-sr-public--present' : '' }} {{ !empty($lite) ? 'cabinet-sr-public--lite' : '' }} {{ !empty($darkTheme) ? 'cabinet-sr-public--dark' : '' }}">
    <div class="cabinet-sr-public__wrap">
        @if(session('success'))
            <div class="cabinet-sr-public__flash">{{ session('success') }}</div>
        @endif
        @include('pages.partials.seo-reports-report-body', [
            'project' => $project,
            'report' => $report,
            'snapshot' => $snapshot,
            'sections' => collect($sections)->map(function ($s) {
                $s['enabled'] = true;
                $s['client_visible'] = true;
                return $s;
            })->all(),
            'isPublicView' => true,
        ])
        @if(empty($presentation) && $report->status === 'ready')
            <form class="cabinet-sr-public__approve" method="post"
                  action="{{ route('seo-reports.public.approve', ['token' => $report->public_token]) }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Approve report') }}</button>
                <span class="small text-secondary">{{ __('Confirm you reviewed this SEO report') }}</span>
            </form>
        @elseif($report->status === 'approved_by_client')
            <p class="cabinet-sr-public__approved">{{ __('Report approved by client') }}
                @if($report->approved_at)
                    · {{ $report->approved_at->format('d.m.Y H:i') }}
                @endif
            </p>
        @endif
        <p class="cabinet-sr-public__footer">
            {{ __('Powered by Titlo') }}
            @if(empty($presentation) && !empty($report->public_token))
                · <a href="{{ route('seo-reports.public.present', ['token' => $report->public_token]) }}">{{ __('Presentation mode') }}</a>
                @if(empty($lite))
                    · <a href="{{ route('seo-reports.public', ['token' => $report->public_token]) }}?lite=1">{{ __('Lite client dashboard') }}</a>
                @else
                    · <a href="{{ route('seo-reports.public', ['token' => $report->public_token]) }}">{{ __('Full report') }}</a>
                @endif
            @endif
        </p>
    </div>
    <script>
        (function () {
            var reactUrl = @json(route('seo-reports.public.react', ['token' => $report->public_token]));
            var token = document.querySelector('meta[name="csrf-token"]');
            document.querySelectorAll('[data-sr-react]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var wrap = btn.closest('[data-sr-react-section]');
                    var section = wrap ? wrap.getAttribute('data-sr-react-section') : '';
                    var type = btn.getAttribute('data-sr-react') || '';
                    var text = '';
                    if (type === 'question' || type === 'clarify') {
                        text = window.prompt(@json(__('Optional comment')), '') || '';
                    }
                    var body = new FormData();
                    body.append('section', section);
                    body.append('type', type);
                    body.append('text', text);
                    body.append('_token', @json(csrf_token()));
                    fetch(reactUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.ok) {
                                btn.classList.add('is-active');
                            }
                        })
                        .catch(function () {});
                });
            });
        })();
    </script>
</body>
</html>
