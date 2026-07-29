<div class="modal fade" id="balance-success-modal" tabindex="-1" aria-labelledby="balance-success-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="balance-success-title">{{ __('Payment credited') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-success mb-3">
                    <i class="bi bi-check-circle-fill display-4"></i>
                </div>
                <p class="mb-0">
                    {{ __('Do not forget to choose a tariff') }}:
                    <a href="{{ route('tariff.index') }}" class="fw-semibold">{{ __('Choose') }}</a>
                </p>
            </div>
        </div>
    </div>
</div>
