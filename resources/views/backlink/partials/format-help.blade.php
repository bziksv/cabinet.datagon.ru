@php
    $example = $example ?? __('Backlink format example');
@endphp
<div class="cabinet-bl-format-help accordion" id="cabinet-bl-format-help">
    <div class="accordion-item border rounded overflow-hidden">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#cabinet-bl-format-help-body" aria-expanded="false"
                    aria-controls="cabinet-bl-format-help-body">
                <i class="bi bi-question-circle me-2 text-primary" aria-hidden="true"></i>
                {{ __('Backlink format help title') }}
            </button>
        </h2>
        <div id="cabinet-bl-format-help-body" class="accordion-collapse collapse"
             data-bs-parent="#cabinet-bl-format-help">
            <div class="accordion-body pt-2 pb-3">
                <p class="small text-secondary mb-2">{{ __('Backlink format help intro') }}</p>
                <code>{{ $example }}</code>
                <dl>
                    <dt>{{ __('Backlink format field donor') }}</dt>
                    <dd>{{ __('Backlink format field donor hint') }}</dd>
                    <dt>{{ __('Backlink format field target') }}</dt>
                    <dd>{{ __('Backlink format field target hint') }}</dd>
                    <dt>{{ __('Backlink format field anchor') }}</dt>
                    <dd>{{ __('Backlink format field anchor hint') }}</dd>
                    <dt>{{ __('Backlink format field nofollow') }}</dt>
                    <dd>{{ __('Backlink format field nofollow hint') }}</dd>
                    <dt>{{ __('Backlink format field noindex') }}</dt>
                    <dd>{{ __('Backlink format field noindex hint') }}</dd>
                </dl>
                <p class="mb-0 small text-secondary">
                    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                    {{ __('Separate the lines using Shift + Enter') }}
                </p>
            </div>
        </div>
    </div>
</div>
