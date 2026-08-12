{{-- Объяснение внешнего модуля вместо пустой таблицы / редиректа. --}}
@php
    $help = \App\Services\SiteAudit\SiteAuditFindingHelp::forCode($code, $meta ?? []);
    $cta = (string) (($meta['external_cta'] ?? null) ?: 'Открыть модуль');
    $related = is_array($externalRelated ?? null) ? $externalRelated : [];
@endphp
<div class="cabinet-sa-ext-module mb-3">
    <div class="cabinet-sa-ext-module__badge">Отдельный модуль Titlo</div>
    <h2 class="cabinet-sa-ext-module__title">{{ $meta['title'] ?? $code }}</h2>
    <p class="cabinet-sa-ext-module__lead mb-0">
        Site Audit обходит <strong>ваш</strong> сайт и собирает замечания по этой проверке.
        Этот пункт — не список ошибок проверки, а вход в смежный инструмент для
        <strong>дополнительного обследования</strong>, если lite-отчётов аудита недостаточно.
    </p>

    <div class="cabinet-sa-help mt-3 mb-0">
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Что это</span>
            <span class="cabinet-sa-help__text">{{ $help['what'] ?? '' }}</span>
        </div>
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Зачем отдельно</span>
            <span class="cabinet-sa-help__text">{{ $help['why'] ?? '' }}</span>
        </div>
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Когда открывать</span>
            <span class="cabinet-sa-help__text">{{ $help['fix'] ?? '' }}</span>
        </div>
    </div>

    @if($related !== [])
        <div class="cabinet-sa-ext-module__related mt-3">
            <div class="cabinet-sa-ext-module__related-title">Уже в этом аудите</div>
            <ul class="cabinet-sa-ext-module__related-list mb-0">
                @foreach($related as $rel)
                    <li>
                        <a href="{{ $rel['href'] }}">{{ $rel['title'] }}</a>
                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ ($rel['count'] ?? 0) > 0 ? 'warning' : 'zero' }}">{{ (int) ($rel['count'] ?? 0) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="cabinet-sa-ext-module__actions mt-3">
        @if(!empty($externalHref))
            <a class="btn btn-primary" href="{{ $externalHref }}" target="_blank" rel="noopener">
                {{ $cta }} <i class="fa fa-external-link" aria-hidden="true"></i>
            </a>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('pages.site-audit.crawl.show', $crawl->id) }}">
            ← К сводке проверки
        </a>
    </div>
</div>
