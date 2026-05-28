@unless(!empty($isPublicView))
    <button type="button"
            class="btn btn-link btn-sm p-0 ms-1 align-baseline cabinet-ta-add-exclude text-nowrap"
            data-word="{{ e($term) }}"
            title="{{ __('Add to exclude list') }}">{{ __('To exclude list') }}</button>
@endunless
