@component('component.card', ['title' => __('Edit Group')])
    @slot('css')
        @include('partners.partials.styles')
    @endslot

    <div class="cabinet-partners-page">
        @include('partners.partials.admin-nav', ['active' => 'admin', 'admin' => true])

        <div class="card shadow-sm border cabinet-partners-form-card">
            <div class="card-body">
                <form action="{{ route('partners.edit.save') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $group->id }}">

                    <div class="mb-3">
                        <label class="form-label" for="name_ru">{{ __('Group Name') }} (ru)</label>
                        <input type="text" name="name_ru" id="name_ru" class="form-control form-control-sm" required value="{{ old('name_ru', $group->name_ru) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name_en">{{ __('Group Name') }} (en)</label>
                        <input type="text" name="name_en" id="name_en" class="form-control form-control-sm" required value="{{ old('name_en', $group->name_en) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="position">{{ __('Position') }}</label>
                        <input type="number" name="position" id="position" class="form-control form-control-sm" required value="{{ old('position', $group->position) }}">
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
@endcomponent
