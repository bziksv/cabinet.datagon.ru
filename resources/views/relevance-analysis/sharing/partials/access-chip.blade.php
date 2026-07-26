@php
    $user = $share->user;
    $email = $user->email ?? '';
    $fullName = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
    $access = (string) $share->access;
@endphp
<span class="cabinet-share-chip" data-share-id="{{ $share->id }}" data-project-id="{{ $share->project_id }}">
    <span class="cabinet-share-chip__email" title="{{ $fullName }}">{{ $email }}</span>
    <select class="form-select form-select-sm access-select" data-share-id="{{ $share->id }}">
        <option value="1" @if($access === '1') selected @endif>{{ __('Viewing only') }}</option>
        <option value="2" @if($access === '2') selected @endif>{{ __('Viewing and launching a re-analysis') }}</option>
    </select>
    <button type="button"
            class="cabinet-share-chip__remove removeAccess"
            data-share-id="{{ $share->id }}"
            title="{{ __('Remove access') }}">&times;</button>
</span>
