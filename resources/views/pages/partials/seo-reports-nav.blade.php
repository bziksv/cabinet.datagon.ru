@php
    $srTab = $srTab ?? 'projects';
    $srContextProject = $srContextProject ?? null;
    $srProjectsTabActive = in_array($srTab, ['projects', 'project', 'settings', 'report', 'compare'], true);
    $srTemplatesTabActive = $srTab === 'templates';
    $srProjectsCount = $srProjectsCount ?? null;
    $srCanEditSettings = $srCanEditSettings ?? false;
@endphp
<div class="cabinet-sr-nav-shell">
    @include('pages.partials.module-beta-banner', [
        'moduleName' => __('Reports'),
    ])
    <nav class="cabinet-sr-tabs" aria-label="{{ __('Reports') }}">
        <a href="{{ route('pages.seo-reports') }}"
           class="cabinet-sr-tabs__item @if($srProjectsTabActive && !$srContextProject) is-active @endif"
           @if($srProjectsTabActive && !$srContextProject) aria-current="page" @endif>
            {{ __('Projects') }}
            @if($srProjectsCount !== null)
                <span class="cabinet-sr-tabs__count">{{ (int) $srProjectsCount }}</span>
            @endif
        </a>
        <a href="{{ route('pages.seo-reports.templates') }}"
           class="cabinet-sr-tabs__item @if($srTemplatesTabActive) is-active @endif"
           @if($srTemplatesTabActive) aria-current="page" @endif>
            {{ __('Templates') }}
        </a>
    </nav>

    @if($srContextProject)
        <div class="cabinet-sr-context" aria-label="{{ __('Project') }}">
            <div class="cabinet-sr-context__main">
                <div class="cabinet-sr-context__crumb">
                    <a href="{{ route('pages.seo-reports') }}">{{ __('Projects') }}</a>
                    <span class="cabinet-sr-context__sep" aria-hidden="true">/</span>
                    <span class="cabinet-sr-context__current">{{ $srContextProject->domain }}</span>
                </div>
                <nav class="cabinet-sr-subnav" aria-label="{{ $srContextProject->domain }}">
                    <a href="{{ route('pages.seo-reports.show', ['id' => $srContextProject->id]) }}"
                       class="cabinet-sr-subnav__item @if($srTab === 'project' || $srTab === 'report') is-active @endif">
                        <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                        {{ __('Overview') }}
                    </a>
                    @if($srCanEditSettings)
                        <a href="{{ route('pages.seo-reports.settings', ['id' => $srContextProject->id]) }}"
                           class="cabinet-sr-subnav__item @if($srTab === 'settings') is-active @endif">
                            <i class="bi bi-gear" aria-hidden="true"></i>
                            {{ __('Settings') }}
                        </a>
                    @endif
                    <a href="{{ route('pages.seo-reports.compare', ['id' => $srContextProject->id]) }}"
                       class="cabinet-sr-subnav__item @if($srTab === 'compare') is-active @endif">
                        <i class="bi bi-columns-gap" aria-hidden="true"></i>
                        {{ __('Compare') }}
                    </a>
                </nav>
            </div>
            <a href="{{ route('pages.seo-reports') }}" class="cabinet-sr-context__back">
                ← {{ __('All SEO report projects') }}
            </a>
        </div>
    @endif
</div>
