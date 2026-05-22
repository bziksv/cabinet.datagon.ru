<div class="modal fade" id="balance-fail-modal" tabindex="-1" aria-labelledby="balance-fail-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="balance-fail-title">{{ __('Payment was not credited') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-circle-fill display-4"></i>
                </div>
                <p class="mb-0 text-secondary">{{ __('Please try again later') }}</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
