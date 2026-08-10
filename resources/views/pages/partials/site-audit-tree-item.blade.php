{{-- Пункт дерева отчётов. Ожидает: $item, $sev, $crawl; опционально $activeCode, $showGroup, $token (public). --}}
@php
    // external = отдельный модуль; в дереве ведём на страницу-объяснение в аудите (не сразу в модуль).
    $isExternal = !empty($item['external']);
    if (!empty($isPublic) && !empty($token)) {
        $itemHref = route('site-audit.public.share.report', [$token, $item['code']]);
    } else {
        $itemHref = route('pages.site-audit.report.show', [$crawl->id, $item['code']]);
    }
    $isActive = (($activeCode ?? '') === ($item['code'] ?? ''));
    $probe = is_array($item['probe'] ?? null) ? $item['probe'] : null;
    $itemCount = (int) ($item['count'] ?? 0);
    // Если находки уже есть — не орём «не было» (статус пробы мог отстать от findings).
    $probeSkipped = !$isExternal
        && $itemCount === 0
        && is_array($probe)
        && ($probe['status'] ?? '') === 'skipped';
@endphp
<a class="cabinet-sa-tree__item {{ $isActive ? 'is-active' : '' }} {{ ($itemCount || $isExternal || $probeSkipped) ? '' : 'is-empty' }}{{ $isExternal ? ' cabinet-sa-tree__item--external' : '' }}{{ $probeSkipped ? ' cabinet-sa-tree__item--skipped' : '' }}"
   href="{{ $itemHref }}"
   data-title="{{ $item['title'] }}"
   data-severity="{{ $sev }}"
   data-count="{{ $itemCount }}"
   @if($isExternal) data-external="1" @endif
   @if($probeSkipped) data-probe-skipped="1" @endif
   title="{{ $isExternal ? 'Отдельный модуль Titlo — сначала объяснение, затем переход' : ($probeSkipped ? ('Не запускалась: ' . \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probe['reason'] ?? null, $probe['probe'] ?? null)) : '') }}">
    <span>
        {{ $item['title'] }}
        <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span>
        @if(!empty($showGroup) && !empty($item['group']))
            <span class="cabinet-sa-group-tag cabinet-sa-group-tag--{{ $item['group'] }}">{{ $item['group'] === 'seo' ? 'SEO' : 'тех' }}</span>
        @endif
    </span>
    @if($isExternal)
        <span class="cabinet-sa-badge cabinet-sa-badge--zero" title="Отдельный модуль — не счётчик ошибок">модуль</span>
    @elseif($probeSkipped)
        <span class="cabinet-sa-badge cabinet-sa-badge--skipped" title="Проверка не запускалась — откройте и нажмите «Запустить»">не было</span>
    @else
        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $itemCount > 0 ? $sev : 'zero' }}">{{ $itemCount }}</span>
    @endif
</a>
