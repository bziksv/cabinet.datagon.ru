@php
    $prefix = $prefix ?? 'ru';
    $localeLabel = $prefix === 'ru' ? 'RU — русская версия' : 'EN — English';
    $visible = $visible ?? false;
    $item = $item ?? null;
@endphp

<div class="cabinet-partners-locale card border shadow-sm mb-3">
    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2 bg-body-tertiary">
        <span class="fw-semibold small mb-0">{{ $localeLabel }}</span>
        <div class="form-check form-switch mb-0">
            <input type="checkbox"
                   class="form-check-input cabinet-partners-locale-toggle"
                   name="auditorium_{{ $prefix }}"
                   id="auditorium_{{ $prefix }}"
                   data-target="{{ $prefix }}"
                   @if($visible) checked @endif>
            <label class="form-check-label small" for="auditorium_{{ $prefix }}">
                {{ __('Partners show in catalog') }}
            </label>
        </div>
    </div>
    <div class="card-body cabinet-partners-locale__panel" id="panel-{{ $prefix }}" @if(!$visible) hidden @endif>
        <div class="mb-3">
            <label class="form-label" for="name_{{ $prefix }}">{{ __('Partner name') }} ({{ $prefix }})</label>
            <input type="text"
                   name="name_{{ $prefix }}"
                   id="name_{{ $prefix }}"
                   class="form-control form-control-sm locale-input locale-input--{{ $prefix }}"
                   value="{{ old('name_' . $prefix, $item ? $item['name_' . $prefix] : '') }}">
        </div>
        <div class="mb-3">
            <label class="form-label" for="link_{{ $prefix }}">{{ __('Link') }} ({{ $prefix }})</label>
            <input type="url"
                   name="link_{{ $prefix }}"
                   id="link_{{ $prefix }}"
                   class="form-control form-control-sm locale-input locale-input--{{ $prefix }}"
                   placeholder="https://"
                   value="{{ old('link_' . $prefix, $item ? $item['link_' . $prefix] : '') }}">
        </div>
        <div class="mb-0">
            <label class="form-label" for="description_{{ $prefix }}">{{ __('Partner description') }} ({{ $prefix }})</label>
            <textarea name="description_{{ $prefix }}"
                      id="description_{{ $prefix }}"
                      rows="4"
                      class="form-control form-control-sm locale-input locale-input--{{ $prefix }}">{{ old('description_' . $prefix, $item ? $item['description_' . $prefix] : '') }}</textarea>
        </div>
    </div>
</div>
