@php
    $subAssigneeOptions = $project
        ? app(\App\Services\SeoChecklist\SeoChecklistService::class)->projectAssigneeOptions($project)
        : [];
@endphp
<div class="cabinet-sc-subtask-form__extras">
    <label class="cabinet-sc-subtask-form__field">
        <span>{{ __('Checklist item deadline') }}</span>
        <input type="date" class="form-control form-control-sm" data-sc-sub-due>
    </label>
    <label class="cabinet-sc-subtask-form__field">
        <span>{{ __('Checklist item assignee') }}</span>
        <select class="form-select form-select-sm" data-sc-sub-assignee>
            <option value="">{{ __('Checklist item not assigned') }}</option>
            @foreach($subAssigneeOptions as $opt)
                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
            @endforeach
        </select>
    </label>
    <label class="cabinet-sc-subtask-form__field cabinet-sc-subtask-form__field--wide">
        <span>{{ __('Comment') }}</span>
        <textarea class="form-control form-control-sm" rows="1" data-sc-sub-comment placeholder="{{ __('Optional') }}"></textarea>
    </label>
    @include('pages.partials.seo-checklist-attach-input')
</div>
