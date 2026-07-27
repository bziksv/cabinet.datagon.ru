@php
    $user = $share->user;
    $email = $user->email ?? '';
    $fullName = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
    $access = (string) $share->access;
@endphp
<div class="cabinet-share-user-row" data-share-id="{{ $share->id }}" data-project-id="{{ $share->project_id }}">
    <span class="cabinet-share-user-row__email" title="{{ $fullName }}">{{ $email }}</span>
    <button type="button"
            class="cabinet-share-user-row__remove removeAccess"
            data-share-id="{{ $share->id }}"
            data-ra-tip="{{ e(__('Remove access')) }}">&times;</button>
</div>
