@component('component.card', ['title' => __('Add group')])
    @slot('css')
        @include('partners.partials.styles')
    @endslot

    <div class="cabinet-partners-page">
        @include('partners.partials.admin-nav', ['active' => 'add-group', 'admin' => true])

        <div class="card shadow-sm border cabinet-partners-form-card">
            <div class="card-body">
                <p class="small text-secondary mb-3">{{ __('Partners add group hint') }}</p>

                <form action="{{ route('partners.save.group') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="name_ru">{{ __('Group Name') }} (ru)</label>
                        <input type="text" name="name_ru" id="name_ru" class="form-control form-control-sm" required value="{{ old('name_ru') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name_en">{{ __('Group Name') }} (en)</label>
                        <input type="text" name="name_en" id="name_en" class="form-control form-control-sm" required value="{{ old('name_en') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="position">{{ __('Position') }}</label>
                        <input type="number" name="position" id="position" class="form-control form-control-sm" required value="{{ old('position') }}">
                        <p class="form-text small mb-0">{{ __('Partners position hint') }}</p>
                    </div>

                    @include('partners.partials.form-errors')

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Add') }}
                        </button>
                        <a href="{{ route('partners.admin') }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endcomponent
