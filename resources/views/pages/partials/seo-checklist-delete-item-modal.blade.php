{{-- Подтверждение удаления задачи / пункта чеклиста --}}
<div class="modal fade" id="cabinetScDeleteItemModal" tabindex="-1" aria-labelledby="cabinetScDeleteItemModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinetScDeleteItemModalTitle">{{ __('Delete task') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-sc-delete-item-lead>
                    {{ __('Delete this task?') }}
                </p>
                <p class="mb-0 small text-secondary text-break" data-sc-delete-item-title></p>
                <p class="mb-0 mt-2 small text-danger">{{ __('This action cannot be undone') }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" data-sc-delete-item-confirm>
                    {{ __('Delete permanently') }}
                </button>
            </div>
        </div>
    </div>
</div>
