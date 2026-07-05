<div class="cabinet-notify-preview-modal">
    <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">
            <i class="bi bi-calendar-x text-warning me-2" aria-hidden="true"></i>
            {{ __('Monitoring schedule paid prompt title') }}
        </h5>
    </div>
    <div class="modal-body pt-2">
        <p class="mb-3">{{ __('Monitoring schedule paid prompt body') }}</p>
        <ul class="list-unstyled small text-secondary mb-0">
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Monitoring schedule paid prompt manual') }}</li>
            <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Monitoring schedule paid prompt saved') }}</li>
            <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __('Monitoring schedule paid prompt upgrade') }}</li>
        </ul>
    </div>
    <div class="modal-footer border-0 flex-wrap gap-2 justify-content-between">
        <button type="button" class="btn btn-outline-secondary" disabled>{{ __('Monitoring schedule paid prompt snooze') }}</button>
        <a href="{{ route('tariff.index') }}" class="btn btn-primary">{{ __('Monitoring schedule paid prompt tariffs') }}</a>
    </div>
</div>
