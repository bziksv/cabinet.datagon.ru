@php
    $attachSvc = app(\App\Services\SeoChecklist\SeoChecklistAttachmentService::class);
    $attachHint = __('Attach files hint', [
        'count' => $attachSvc->maxFiles(),
        'size' => number_format($attachSvc->maxKb() / 1024, 0, '', ' '),
    ]);
@endphp
<div class="cabinet-sc-attach" data-sc-attach>
    <label class="cabinet-sc-attach__btn" data-tip="{{ $attachHint }}">
        <input type="file"
               class="visually-hidden"
               multiple
               accept="{{ $attachSvc->acceptAttribute() }}"
               data-sc-attach-input
               data-max-files="{{ $attachSvc->maxFiles() }}"
               data-max-bytes="{{ $attachSvc->maxKb() * 1024 }}">
        <i class="bi bi-paperclip" aria-hidden="true"></i>
        <span>{{ __('Attach file') }}</span>
    </label>
    <span class="cabinet-sc-attach__list" data-sc-attach-list></span>
</div>
