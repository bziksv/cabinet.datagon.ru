@php
    $help = \App\Services\SiteAudit\SiteAuditFindingHelp::forCode($code, $meta ?? []);
@endphp
<div class="cabinet-sa-help mb-3">
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Что это</span>
        <span class="cabinet-sa-help__text">{{ $help['what'] ?? '' }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Почему плохо</span>
        <span class="cabinet-sa-help__text">{{ $help['why'] ?? '' }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Как исправить</span>
        <span class="cabinet-sa-help__text">{{ $help['fix'] ?? '' }}</span>
    </div>
    @if(!empty($showReferrers) && !empty($crawl))
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Откуда ссылки</span>
            <span class="cabinet-sa-help__text">
                @if(in_array($code ?? '', ['redirect', 'redirect_chain_long', 'redirect_loop'], true))
                    Колонка «Откуда ссылаются» — страницы, где в HTML стоит ссылка на этот URL
                    (меню, футер, текст), либо откуда URL попал в очередь: <strong>sitemap.xml</strong>, посев, главная.
                    Чинить имеет смысл там: в ссылках сразу писать финальный адрес со слэшем/без, как на сервере.
                    Фильтр «Тип редиректа» → «Другая страница» скрывает массовые /about → /about/ и оставляет смену URL.
                @else
                    Колонка «Откуда ссылаются» — либо страницы с HTML-ссылкой на этот URL,
                    либо откуда он попал в очередь: <strong>sitemap.xml</strong>, посев (список URL), главная.
                    Обратный отчёт:
                    <a href="{{ route('pages.site-audit.report.show', [$crawl->id, 'page_has_broken_links']) }}">Страницы с битыми ссылками</a>
                    (там URL = страница-источник, в деталях — битые цели).
                @endif
            </span>
        </div>
    @endif
</div>
