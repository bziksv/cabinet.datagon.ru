@php
    $noteAttachments = $note->relationLoaded('attachments') ? $note->attachments : collect();
    $attProjectId = (int) ($projectId ?? optional($note->item)->project_id);
@endphp
@if($noteAttachments->isNotEmpty() && $attProjectId > 0)
    <div class="cabinet-sc-attachments-wrap" data-sc-gallery>
        <ul class="cabinet-sc-attachments">
            @foreach($noteAttachments as $att)
                @php
                    $attUrl = route('pages.seo-checklist.attachment', ['id' => $attProjectId, 'attachmentId' => $att->id]);
                @endphp
                <li class="cabinet-sc-attachments__item @if($att->isImage()) is-image @endif">
                    @if($att->isImage())
                        <a href="{{ $attUrl }}"
                           class="cabinet-sc-attachments__thumb"
                           data-sc-gallery-img
                           data-name="{{ $att->original_name }}">
                            <img src="{{ $attUrl }}" alt="{{ $att->original_name }}" loading="lazy">
                        </a>
                    @endif
                    <a href="{{ $attUrl }}"
                       class="cabinet-sc-attachments__link"
                       @if($att->isImage()) data-sc-gallery-open @else target="_blank" rel="noopener" @endif>
                        <i class="bi {{ $att->iconClass() }}" aria-hidden="true"></i>
                        <span class="cabinet-sc-attachments__name">{{ $att->original_name }}</span>
                        <span class="cabinet-sc-attachments__size">{{ $att->sizeLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if($noteAttachments->count() > 1)
            <a href="{{ route('pages.seo-checklist.note.attachments.zip', ['id' => $attProjectId, 'noteId' => $note->id]) }}"
               class="cabinet-sc-attachments__zip">
                <i class="bi bi-download" aria-hidden="true"></i>
                {{ __('Download all files', ['count' => $noteAttachments->count()]) }}
            </a>
        @endif
    </div>
@endif
