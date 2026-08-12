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
    $probePending = !$isExternal
        && $itemCount === 0
        && is_array($probe)
        && ($probe['status'] ?? '') === 'pending';
    $showXmlBadges = empty($isPublic)
        && auth()->check()
        && method_exists(auth()->user(), 'hasRole')
        && auth()->user()->hasRole(['admin', 'Super Admin']);
    $usesXml = !empty($item['uses_xml']);
@endphp
<a class="cabinet-sa-tree__item {{ $isActive ? 'is-active' : '' }} {{ ($itemCount || $isExternal || $probeSkipped || $probePending) ? '' : 'is-empty' }}{{ $isExternal ? ' cabinet-sa-tree__item--external' : '' }}{{ $probeSkipped ? ' cabinet-sa-tree__item--skipped' : '' }}{{ $probePending ? ' cabinet-sa-tree__item--pending' : '' }}{{ ($showXmlBadges && $usesXml) ? ' cabinet-sa-tree__item--xml' : '' }}"
   href="{{ $itemHref }}"
   data-title="{{ $item['title'] }}"
   data-severity="{{ $sev }}"
   data-count="{{ $itemCount }}"
   @if($isExternal) data-external="1" @endif
   @if($probeSkipped) data-probe-skipped="1" @endif
   @if($probePending) data-probe-pending="1" @endif
   @if($showXmlBadges && $usesXml) data-xml="1" @endif
   title="{{ $isExternal ? 'Отдельный модуль Titlo — сначала объяснение, затем переход' : ($probeSkipped ? ('Не запускалась: ' . \App\Services\SiteAudit\SiteAuditProbeStatus::reasonLabel($probe['reason'] ?? null, $probe['probe'] ?? null)) : ($probePending ? 'Проверка уникальности сейчас выполняется' : '')) }}{{ ($showXmlBadges && $usesXml) ? ((($isExternal || $probeSkipped || $probePending) ? ' · ' : '') . 'При запуске ходит в поисковую выдачу (платный лимит)') : '' }}">
    <span class="cabinet-sa-tree__item-main">
        @if($showXmlBadges && $usesXml)
            <span class="cabinet-sa-xml-tag" title="Не «уже проверено». Метка: при запуске этой проверки идут запросы к поисковой выдаче">выдача</span>
        @endif
        <span class="cabinet-sa-tree__item-title">
            {{ $item['title'] }}
            <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span>
            @if(!empty($showGroup) && !empty($item['group']))
                <span class="cabinet-sa-group-tag cabinet-sa-group-tag--{{ $item['group'] }}">{{ $item['group'] === 'seo' ? 'SEO' : 'тех' }}</span>
            @endif
        </span>
    </span>
    @if($isExternal)
        <span class="cabinet-sa-badge cabinet-sa-badge--zero" title="Отдельный модуль — не счётчик ошибок">модуль</span>
    @elseif($probePending)
        <span class="cabinet-sa-badge cabinet-sa-badge--pending" title="Идёт проверка уникальности">идёт</span>
    @elseif($probeSkipped)
        <span class="cabinet-sa-badge cabinet-sa-badge--skipped" title="{{ ($probe['probe'] ?? '') === 'plagiarism_external' ? 'Ещё не запускали — вкладка «Антиплагиат» на сводке проверки' : 'Проверка не запускалась — откройте и нажмите «Запустить»' }}">не было</span>
    @else
        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $itemCount > 0 ? $sev : 'zero' }}">{{ $itemCount }}</span>
    @endif
</a>
