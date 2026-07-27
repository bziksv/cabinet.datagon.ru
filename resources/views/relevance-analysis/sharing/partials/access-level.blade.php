@php
    $access = (string) $share->access;
@endphp
<div class="cabinet-share-user-row" data-share-id="{{ $share->id }}" data-project-id="{{ $share->project_id }}">
    <select class="form-select form-select-sm access-select" data-share-id="{{ $share->id }}">
        <option value="1" @if($access === '1') selected @endif>{{ __('Viewing only') }}</option>
        <option value="2" @if($access === '2') selected @endif>{{ __('Relevance share access reanalyse short') }}</option>
    </select>
</div>
