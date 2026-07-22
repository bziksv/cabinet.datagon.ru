{{-- Пункт дерева отчётов. Ожидает: $item, $sev, $crawl; опционально $activeCode, $showGroup, $token (public). --}}
@php
    $isExternal = !empty($item['external']) && !empty($item['href']);
    if ($isExternal) {
        $itemHref = $item['href'];
    } elseif (!empty($isPublic) && !empty($token)) {
        $itemHref = route('site-audit.public.share.report', [$token, $item['code']]);
    } else {
        $itemHref = route('pages.site-audit.report.show', [$crawl->id, $item['code']]);
    }
    $isActive = !$isExternal && (($activeCode ?? '') === ($item['code'] ?? ''));
@endphp
<a class="cabinet-sa-tree__item {{ $isActive ? 'is-active' : '' }} {{ $item['count'] || $isExternal ? '' : 'is-empty' }}{{ $isExternal ? ' cabinet-sa-tree__item--external' : '' }}"
   href="{{ $itemHref }}"
   @if($isExternal) target="_blank" rel="noopener" @endif
   data-title="{{ $item['title'] }}"
   data-severity="{{ $sev }}"
   data-count="{{ (int) $item['count'] }}"
   @if($isExternal) data-external="1" @endif
   title="{{ $isExternal ? 'Откроется в новой вкладке — отдельный модуль Titlo' : '' }}">
    <span>
        {{ $item['title'] }}
        @if($isExternal)
            <i class="fa fa-external-link" aria-hidden="true"></i>
        @endif
        <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span>
        @if(!empty($showGroup) && !empty($item['group']))
            <span class="cabinet-sa-group-tag cabinet-sa-group-tag--{{ $item['group'] }}">{{ $item['group'] === 'seo' ? 'SEO' : 'тех' }}</span>
        @endif
    </span>
    @if($isExternal)
        <span class="cabinet-sa-badge cabinet-sa-badge--zero" title="Модуль Titlo">модуль</span>
    @else
        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
    @endif
</a>
