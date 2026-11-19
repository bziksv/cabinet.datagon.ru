<label class="cabinet-sc-plan-filters__project">
    <span class="visually-hidden">{{ __('Filter by project') }}</span>
    <select class="form-select form-select-sm"
            data-sc-plan-project
            data-placeholder="{{ __('All projects') }}">
        <option value=""></option>
        @foreach(($filterProjects ?? []) as $fp)
            @php
                $fpDomain = trim((string) ($fp->domain ?? ''));
                $fpTitle = trim((string) ($fp->title ?? ''));
                $fpTpl = $fp->relationLoaded('template') && $fp->template
                    ? trim((string) ($fp->template->title ?? ''))
                    : '';
                $fpTitleUseful = $fpTitle !== ''
                    && strcasecmp($fpTitle, $fpDomain) !== 0
                    && ($fpDomain === '' || mb_stripos($fpTitle, $fpDomain) === false)
                    && ($fpTpl === '' || strcasecmp($fpTitle, $fpTpl) !== 0);
                $fpLabel = implode(' · ', array_filter([
                    $fpDomain,
                    $fpTpl,
                    $fpTitleUseful ? $fpTitle : '',
                ], 'strlen'));
            @endphp
            <option value="{{ $fp->id }}">{{ $fpLabel }}</option>
        @endforeach
    </select>
</label>
<label class="cabinet-sc-plan-filters__my-role">
    <span class="visually-hidden">{{ __('Filter by my role') }}</span>
    <select class="form-select form-select-sm" data-sc-plan-my-role>
        <option value="">{{ __('All my roles') }}</option>
        <option value="owner">{{ __('I am responsible') }}</option>
        <option value="auditor">{{ __('I am auditor') }}</option>
        <option value="pm">{{ __('I am PM') }}</option>
        <option value="participant">{{ __('I am participant') }}</option>
        <option value="account">{{ __('I created checklist') }}</option>
    </select>
</label>
