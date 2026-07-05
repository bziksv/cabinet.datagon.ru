<div class="modal fade" id="removeHistoryReport" tabindex="-1" aria-labelledby="removeHistoryReportLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="removeHistoryReportLabel">{{ __('Monitoring comp dynamics delete title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0 text-secondary">
                    {{ __('Monitoring comp dynamics delete body') }}
                    <strong id="comp-dynamics-delete-range" class="text-body"></strong>
                </p>
            </div>
            <div class="modal-footer border-top d-flex flex-wrap justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="remove-history-report-confirm">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('Remove') }}
                </button>
            </div>
        </div>
    </div>
</div>
