@if(!empty($isMonitoringAdmin))
    <div id="cabinet-mon-v2-admin-debug" class="cabinet-mon-v2-admin-debug card card-outline card-secondary shadow-sm mt-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h3 class="card-title h6 mb-0">
                <i class="bi bi-bug me-1 text-secondary" aria-hidden="true"></i>{{ __('Extended progress log (admin)') }}
            </h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-xs btn-outline-secondary btn-sm" id="cabinet-mon-v2-debug-copy">
                    <i class="bi bi-clipboard me-1"></i>{{ __('Copy') }}
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary btn-sm" id="cabinet-mon-v2-debug-clear">
                    {{ __('Clear view') }}
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="cabinet-mon-v2-admin-debug-meta small text-secondary px-3 py-2 border-bottom">
                <span>{{ __('Session') }}:</span> <code id="cabinet-mon-v2-debug-session">—</code>
                <span class="ms-2">{{ __('Request') }}:</span> <span id="cabinet-mon-v2-debug-req">0</span>
            </div>
            <pre id="cabinet-mon-v2-debug-log" class="cabinet-mon-v2-admin-debug-log mb-0" aria-live="polite"></pre>
        </div>
    </div>
@endif
