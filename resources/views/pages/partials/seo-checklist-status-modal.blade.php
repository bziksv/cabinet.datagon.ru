{{-- Выбор статуса после остановки таймера --}}
<div class="modal fade cabinet-sc-status-modal" id="cabinetScStatusModal" tabindex="-1" aria-labelledby="cabinetScStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="cabinet-sc-status-modal__heading">
                    <span class="cabinet-sc-status-modal__badge" aria-hidden="true">
                        <i class="bi bi-stopwatch"></i>
                    </span>
                    <div>
                        <h5 class="modal-title" id="cabinetScStatusModalTitle">{{ __('Choose task status') }}</h5>
                        <p class="cabinet-sc-status-modal__lead mb-0" data-sc-status-modal-lead>
                            {{ __('Choose status after stop hint') }}
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <div class="cabinet-sc-status-modal__list" data-sc-status-modal-list role="radiogroup" aria-labelledby="cabinetScStatusModalTitle"></div>
                <div class="cabinet-sc-status-modal__note d-none" data-sc-status-modal-note-wrap>
                    <label class="form-label small mb-1" for="cabinetScStatusModalNote">{{ __('Comment') }}</label>
                    <textarea id="cabinetScStatusModalNote"
                              class="form-control form-control-sm"
                              rows="2"
                              data-sc-status-modal-note
                              placeholder="{{ __('Comment required for this status') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" data-sc-status-modal-save>
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </div>
</div>
