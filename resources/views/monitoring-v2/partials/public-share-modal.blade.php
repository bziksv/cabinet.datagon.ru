<div class="modal fade cabinet-mon-v2-public-share-modal" id="cabinetMonV2PublicShareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title mb-1" id="cabinetMonV2PublicShareModalTitle">{{ __('Monitoring v2 public share modal title') }}</h5>
                    <p class="text-secondary small mb-0" id="cabinetMonV2PublicShareModalSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body pt-3" id="cabinetMonV2PublicShareModalBody">
                <div class="text-center py-5 text-secondary">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-2 mb-0 small">{{ __('Loading') }}…</p>
                </div>
            </div>
            <div class="modal-footer border-top cabinet-mon-v2-public-share-modal__footer">
                <div class="cabinet-mon-v2-public-share-actions w-100">
                    <div class="cabinet-mon-v2-public-share-panel rounded border bg-white p-2"
                         id="cabinetMonV2PublicSharePanel"
                         data-feature-available="{{ \App\MonitoringPublicShare::tableAvailable() ? '1' : '0' }}"
                         data-create-url="{{ route('monitoring.public.share.create') }}"
                         data-revoke-url="{{ route('monitoring.public.share.revoke') }}">
                        <div class="alert alert-warning py-2 px-2 small mb-2 d-none" id="cabinetMonV2PublicShareUnavailable" role="alert">
                            {{ __('Monitoring public share unavailable') }}
                        </div>
                        <div class="small fw-semibold mb-2">
                            <i class="bi bi-share me-1" aria-hidden="true"></i>{{ __('Public link without registration') }}
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <input type="text"
                                   class="form-control font-monospace"
                                   id="cabinetMonV2PublicShareUrl"
                                   readonly
                                   placeholder="{{ __('Create a public link to copy it here') }}">
                            <button type="button" class="btn btn-primary" id="cabinetMonV2PublicShareCopy" disabled>
                                <i class="bi bi-clipboard" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @php($shareTtlOptions = \App\Support\MonitoringPublicShareTtl::labelsForUi())
                            <label class="visually-hidden" for="cabinetMonV2PublicShareTtl">{{ __('Monitoring share ttl label') }}</label>
                            <select class="form-select form-select-sm cabinet-mon-v2-public-share__ttl"
                                    id="cabinetMonV2PublicShareTtl"
                                    aria-label="{{ __('Monitoring share ttl label') }}">
                                @foreach($shareTtlOptions as $days => $label)
                                    <option value="{{ $days }}" @if((int) $days === 30) selected @endif>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-primary btn-sm" id="cabinetMonV2PublicShareCreate">
                                <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>{{ __('Create public link') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cabinetMonV2PublicShareRevoke" disabled>
                                {{ __('Revoke public link') }}
                            </button>
                            <span class="badge rounded-pill text-bg-secondary d-none" id="cabinetMonV2PublicShareExpires"></span>
                        </div>
                        <p class="small text-secondary mb-0 mt-2">{{ __('Monitoring public share hint ttl') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
