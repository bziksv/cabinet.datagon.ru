@php
    $srTab = $srTab ?? 'projects';
    $srContextProject = $srContextProject ?? null;
    $srProjectsTabActive = in_array($srTab, ['projects', 'project', 'settings', 'report', 'compare'], true);
    $srTemplatesTabActive = $srTab === 'templates';
    $srProjectsCount = $srProjectsCount ?? null;
@endphp
<div class="cabinet-sr-nav-shell">
    <nav class="cabinet-sr-tabs" aria-label="{{ __('SEO Reports') }}">
        <a href="{{ route('pages.seo-reports') }}"
           class="cabinet-sr-tabs__item @if($srProjectsTabActive) is-active @endif"
           @if($srProjectsTabActive) aria-current="page" @endif>
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
            <div class="cabinet-sr-context__crumb">
                <a href="{{ route('pages.seo-reports') }}">{{ __('Projects') }}</a>
                <span class="cabinet-sr-context__sep" aria-hidden="true">/</span>
                @if(in_array($srTab, ['settings', 'report', 'compare'], true))
                    <a href="{{ route('pages.seo-reports.show', ['id' => $srContextProject->id]) }}">{{ $srContextProject->domain }}</a>
                    <span class="cabinet-sr-context__sep" aria-hidden="true">/</span>
                    <span class="cabinet-sr-context__current">
                        @if($srTab === 'settings')
                            {{ __('Settings') }}
                        @elseif($srTab === 'compare')
                            {{ __('Compare') }}
                        @else
                            {{ __('Report') }}
                        @endif
                    </span>
                @else
                    <span class="cabinet-sr-context__current">{{ $srContextProject->domain }}</span>
                @endif
            </div>
            <a href="{{ route('pages.seo-reports') }}" class="cabinet-sr-context__back">
                ← {{ __('All SEO report projects') }}
            </a>
        </div>
    @endif
</div>
