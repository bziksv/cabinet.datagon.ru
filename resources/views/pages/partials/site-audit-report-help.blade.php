@php
    $help = \App\Services\SiteAudit\SiteAuditFindingHelp::forCode($code, $meta ?? []);
@endphp
<div class="cabinet-sa-help mb-3">
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Что это</span>
        <span class="cabinet-sa-help__text">{{ $help['what'] }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Почему плохо</span>
        <span class="cabinet-sa-help__text">{{ $help['why'] }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Как исправить</span>
        <span class="cabinet-sa-help__text">{{ $help['fix'] }}</span>
    </div>
    @if(!empty($showReferrers) && !empty($crawl))
        <div class="cabinet-sa-help__row">
            <span class="cabinet-sa-help__label">Откуда ссылки</span>
            <span class="cabinet-sa-help__text">
                Колонка «Откуда ссылаются» — страницы краула, где в HTML есть ссылка на этот URL.
                Если написано «из sitemap» — URL взяли из sitemap.xml при старте обхода (не из кликабельной ссылки).
                Обратный отчёт:
                <a href="{{ route('pages.site-audit.report.show', [$crawl->id, 'page_has_broken_links']) }}">Страницы с битыми ссылками</a>
                (там URL = страница-источник, в деталях — битые цели).
            </span>
        </div>
    @endif
</div>
