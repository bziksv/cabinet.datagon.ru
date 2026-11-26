{{--
  Страницы по задачам аудита в HTML-отчёте: сворачиваемые блоки.
  $auditGroups — из SeoReportAuditPages::groups(); $isPublic — публичная страница.
--}}
@if(!empty($auditGroups))
    <div class="cabinet-sr-audit-pages mt-2">
        @foreach($auditGroups as $g)
            <details class="small mb-1">
                <summary>{{ $g['title'] }}</summary>
                <ul class="mt-1 mb-1">
                    @foreach($g['urls'] as $u)
                        <li><a href="{{ $u }}" target="_blank" rel="nofollow noopener" class="text-break">{{ $u }}</a></li>
                    @endforeach
                </ul>
                @if($g['total'] > count($g['urls']))
                    <div class="text-secondary">… {{ __('Audit pages full list in excel') }}</div>
                @endif
                @if(!$isPublic && $g['report_path'] !== '')
                    <a href="{{ url($g['report_path']) }}">{{ __('Open audit report') }}</a>
                @endif
            </details>
        @endforeach
    </div>
@endif
