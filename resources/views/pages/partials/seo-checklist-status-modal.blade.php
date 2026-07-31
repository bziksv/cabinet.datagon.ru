{{-- Выбор статуса после остановки таймера --}}
<div class="modal fade" id="cabinetScStatusModal" tabindex="-1" aria-labelledby="cabinetScStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cabinetScStatusModalTitle">{{ __('Choose task status') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-3" data-sc-status-modal-lead>
                    {{ __('Choose status after stop hint') }}
                </p>
                <div class="cabinet-sc-status-modal__list" data-sc-status-modal-list role="radiogroup" aria-labelledby="cabinetScStatusModalTitle"></div>
                <div class="cabinet-sc-status-modal__note d-none mt-3" data-sc-status-modal-note-wrap>
                    <label class="form-label small mb-1" for="cabinetScStatusModalNote">{{ __('Comment') }}</label>
                    <textarea id="cabinetScStatusModalNote"
                              class="form-control form-control-sm"
                              rows="2"
                              data-sc-status-modal-note
                              placeholder="{{ __('Comment required for this status') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" data-sc-status-modal-save>{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
