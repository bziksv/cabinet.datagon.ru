@include('monitoring.competitors.partials.remove-competitor-modal')

<div class="modal fade" id="addCompetitorManualModal" tabindex="-1" aria-labelledby="addCompetitorManualModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCompetitorManualModalLabel">{{ __('Monitoring competitors add manual title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">{{ __('Monitoring competitors add manual help') }}</p>
                <label class="form-label fw-semibold" for="competitor-manual-input">{{ __('Domain') }}</label>
                <textarea id="competitor-manual-input" class="form-control" rows="5"
                          placeholder="{{ __('Monitoring competitors add manual placeholder') }}"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="save-competitor-manual">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add') }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="competitorsModal" tabindex="-1" aria-labelledby="competitorsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="competitorsModalLabel">{{ __('Monitoring competitors suggest modal title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">{{ __('Monitoring competitors suggest modal help') }}</p>
                <div class="mb-3">
                    <label for="competitors-textarea" class="form-label fw-semibold">{{ __('Your closest competitors') }}</label>
                    <textarea name="competitors-textarea" id="competitors-textarea" class="form-control" rows="8"></textarea>
                </div>
                <div class="mb-3">
                    <button class="btn btn-outline-secondary btn-sm mb-2" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseIgnoredDomains" aria-expanded="false"
                            aria-controls="collapseIgnoredDomains">
                        {{ __('Ignored domains') }}
                    </button>
                    <div class="collapse" id="collapseIgnoredDomains">
                        <textarea id="ignored-domains" name="ignored-domains" class="form-control" rows="6" readonly>{{ $ignoredDomains }}</textarea>
                    </div>
                </div>
                <div>
                    <p class="fw-semibold small mb-1">{{ __('Domain') }}: {{ __('How many times have I met') }}</p>
                    <div id="competitors-list" class="small text-secondary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" id="add-competitors" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Add') }}</button>
            </div>
        </div>
    </div>
</div>
