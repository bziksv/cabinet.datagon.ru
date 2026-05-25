<div class="modal fade" id="saveUrlsModal" tabindex="-1" aria-labelledby="saveUrlsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saveUrlsModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="relevanceUrls">
                    {{ __('Select the url that will be saved for each phrase of this cluster') }}
                </label>
                <select name="relevanceUrls" id="relevanceUrls" class="form-select"></select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="save-cluster-url-button" data-bs-dismiss="modal">
                    {{ __('Save') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>
