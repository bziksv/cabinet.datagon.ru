@php
    $scTab = $scTab ?? 'projects';
    $scModuleTitle = $seoChecklistModuleTitle
        ?? \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id());
@endphp
<nav class="cabinet-sc-tabs" aria-label="{{ $scModuleTitle }}">
    <a href="{{ route('pages.seo-checklist.chronicle') }}"
       class="cabinet-sc-tabs__item @if($scTab === 'chronicle') is-active @endif"
       @if($scTab === 'chronicle') aria-current="page" @endif>
        {{ __('Chronicle') }}
        @if(isset($scUnreadNotesCount) && (int) $scUnreadNotesCount > 0)
            <span class="cabinet-sc-tabs__count is-hot">{{ $scUnreadNotesCount }}</span>
        @endif
    </a>
    <a href="{{ route('pages.seo-checklist.my-tasks') }}"
       class="cabinet-sc-tabs__item @if($scTab === 'my-tasks') is-active @endif"
       @if($scTab === 'my-tasks') aria-current="page" @endif>
        {{ __('My tasks') }}
        @if(isset($scMyTasksCount))
            <span class="cabinet-sc-tabs__count @if((int) $scMyTasksCount > 0) is-hot @endif">{{ $scMyTasksCount }}</span>
        @endif
    </a>
    @if(!empty($scShowReviewTab))
        <a href="{{ route('pages.seo-checklist.review') }}"
           class="cabinet-sc-tabs__item @if($scTab === 'review') is-active @endif"
           @if($scTab === 'review') aria-current="page" @endif>
            {{ __('For review') }}
            @if(isset($scReviewCount))
                <span class="cabinet-sc-tabs__count @if((int) $scReviewCount > 0) is-hot @endif">{{ $scReviewCount }}</span>
            @endif
        </a>
    @endif
    <a href="{{ route('pages.seo-checklist.timesheet') }}"
       class="cabinet-sc-tabs__item @if($scTab === 'timesheet') is-active @endif"
       @if($scTab === 'timesheet') aria-current="page" @endif>
        {{ __('Time log') }}
    </a>
    <a href="{{ route('pages.seo-checklist') }}"
       class="cabinet-sc-tabs__item @if($scTab === 'projects') is-active @endif"
       @if($scTab === 'projects') aria-current="page" @endif>
        {{ __('Projects') }}
        @if(isset($scProjectsCount))
            <span class="cabinet-sc-tabs__count">{{ $scProjectsCount }}</span>
        @endif
    </a>
    <a href="{{ route('pages.seo-checklist.team') }}"
       class="cabinet-sc-tabs__item @if($scTab === 'team') is-active @endif"
       @if($scTab === 'team') aria-current="page" @endif>
        {{ __('Team') }}
        @if(isset($scTeamCount))
            <span class="cabinet-sc-tabs__count">{{ $scTeamCount }}</span>
        @endif
    </a>
    <a href="{{ route('pages.seo-checklist.templates') }}"
       class="cabinet-sc-tabs__item @if($scTab === 'templates') is-active @endif"
       @if($scTab === 'templates') aria-current="page" @endif>
        {{ __('Templates') }}
        @if(isset($scTemplatesCount))
            <span class="cabinet-sc-tabs__count">{{ $scTemplatesCount }}</span>
        @endif
    </a>
    <details class="cabinet-sc-module-rename">
        <summary class="cabinet-sc-module-rename__btn" title="{{ __('Rename section') }}">
            <i class="bi bi-pencil" aria-hidden="true"></i>
            <span>{{ __('Rename') }}</span>
        </summary>
        <form method="post"
              action="{{ route('pages.seo-checklist.module-title') }}"
              class="cabinet-sc-module-rename__form">
            @csrf
            <label class="cabinet-sc-module-rename__label" for="scModuleTitleInput">{{ __('Section name') }}</label>
            <input id="scModuleTitleInput"
                   type="text"
                   name="module_title"
                   class="form-control form-control-sm"
                   value="{{ $scModuleTitle }}"
                   maxlength="40"
                   placeholder="{{ __('SEO Checklist') }}"
                   autocomplete="off">
            <p class="cabinet-sc-module-rename__hint">{{ __('Section name hint') }}</p>
            <div class="cabinet-sc-module-rename__actions">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </details>
</nav>
