{{-- Цепочка: Аудит · проекты → сайт → проверка → (отчёт|сравнение) --}}
@php
    $crawl = $crawl ?? null;
    $project = $project ?? optional($crawl)->project;
    $domain = trim((string) (optional($project)->domain ?? ''));
    if ($domain === '') {
        $domain = 'сайт';
    }
    $level = $level ?? 'crawl'; // crawl|report|diff
    $reportTitle = trim((string) ($reportTitle ?? ''));
    $reportSeverity = trim((string) ($reportSeverity ?? ''));
    $projectsUrl = route('pages.site-audit');
    $siteUrl = $domain !== 'сайт'
        ? route('pages.site-audit', ['domain' => $domain]) . '#sa-history'
        : $projectsUrl . '#sa-projects';
    $crawlUrl = $crawl ? route('pages.site-audit.crawl.show', $crawl->id) : null;
@endphp
<nav class="cabinet-sa-crumbs" aria-label="Навигация по аудиту">
    <a href="{{ $projectsUrl }}">Аудит · проекты</a>
    <span class="cabinet-sa-crumbs__sep" aria-hidden="true">/</span>
    <a href="{{ $siteUrl }}" title="История и сайты">{{ $domain }}</a>
    @if($crawl)
        <span class="cabinet-sa-crumbs__sep" aria-hidden="true">/</span>
        @if($level === 'crawl')
            <span class="cabinet-sa-crumbs__current" aria-current="page">Проверка #{{ $crawl->id }}</span>
        @else
            <a href="{{ $crawlUrl }}">Проверка #{{ $crawl->id }}</a>
        @endif
    @endif
    @if($level === 'report' && $reportTitle !== '')
        <span class="cabinet-sa-crumbs__sep" aria-hidden="true">/</span>
        <span class="cabinet-sa-crumbs__current" aria-current="page">
            {{ \Illuminate\Support\Str::limit($reportTitle, 48) }}
            @if($reportSeverity !== '')
                {!! \App\Services\SiteAudit\SiteAuditFindingPresenter::severityBadgeHtml($reportSeverity) !!}
            @endif
        </span>
    @elseif($level === 'diff')
        <span class="cabinet-sa-crumbs__sep" aria-hidden="true">/</span>
        <span class="cabinet-sa-crumbs__current" aria-current="page">Сравнение</span>
    @endif
</nav>
