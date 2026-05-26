@component('component.card', ['title' => __('Edit information about partner ')])
    @slot('css')
        @include('partners.partials.styles')
    @endslot

    <div class="cabinet-partners-page">
        @include('partners.partials.admin-nav', ['active' => 'admin', 'admin' => true])

        <div class="card shadow-sm border cabinet-partners-form-card">
            <div class="card-body">
                <form action="{{ route('partners.save.edit.item') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id" value="{{ $item->id }}">

                    <div class="mb-3">
                        <label class="form-label" for="partners_groups_id">{{ __('Group Name') }}</label>
                        <select name="partners_groups_id" id="partners_groups_id" class="form-select form-select-sm" required>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @if(old('partners_groups_id', $item->partners_groups_id) == $group->id) selected @endif>
                                    {{ $group->name_ru }} / {{ $group->name_en }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="position">{{ __('Position') }}</label>
                        <input type="number" name="position" id="position" class="form-control form-control-sm" required value="{{ old('position', $item->position) }}">
                    </div>

                    @include('partners.partials.locale-fields', [
                        'prefix' => 'ru',
                        'visible' => (bool) old('auditorium_ru', $item->auditorium_ru),
                        'item' => $item,
                    ])
                    @include('partners.partials.locale-fields', [
                        'prefix' => 'en',
                        'visible' => (bool) old('auditorium_en', $item->auditorium_en),
                        'item' => $item,
                    ])

                    <div class="mb-3">
                        <label class="form-label">{{ __('Current Image') }}</label>
                        <div class="mb-2">
                            <img class="cabinet-partners-preview-img"
                                 src="{{ cabinet_storage_url($item['image']) }}"
                                 alt="{{ $item['name_ru'] ?? '' }}">
                        </div>
                        <label class="form-label" for="image">{{ __('Image') }}</label>
                        <input type="file"
                               name="image"
                               id="image"
                               class="form-control form-control-sm"
                               accept=".jpg,.jpeg,.png">
                        <p class="form-text small mb-0">{{ __('Choose new img') }}</p>
                    </div>

                    @include('partners.partials.form-errors')

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Save') }}
                        </button>
                        <a href="{{ route('partners.admin') }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @slot('js')
        @include('partners.partials.locale-fields-js')
    @endslot
@endcomponent
