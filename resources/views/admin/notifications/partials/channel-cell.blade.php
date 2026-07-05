@if(!empty($on))
    <span class="text-success" aria-label="{{ __('Yes') }}"><i class="bi bi-check-circle-fill"></i></span>
@else
    <span class="text-secondary opacity-50" aria-label="{{ __('No') }}"><i class="bi bi-dash-lg"></i></span>
@endif
