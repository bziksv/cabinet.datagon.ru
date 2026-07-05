<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="callout callout-info h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Monitoring offset guide what') }}</h5>
                    <p class="small mb-0">{{ __('Monitoring offset guide what body') }}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-warning h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Monitoring offset guide caution') }}</h5>
                    <p class="small mb-0">{!! __('Monitoring offset guide caution body') !!}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-success h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-list-ol me-1"></i>{{ __('Monitoring offset guide steps') }}</h5>
                    <ol class="small mb-0 ps-3">
                        <li>{{ __('Monitoring offset guide step project') }}</li>
                        <li>{{ __('Monitoring offset guide step rules') }}</li>
                        <li>{{ __('Monitoring offset guide step export') }}</li>
                        <li>{{ __('Monitoring offset guide step download') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white py-3">
        <h3 class="h6 mb-1">{{ __('Monitoring offset logic title') }}</h3>
        <p class="small text-secondary mb-0">{{ __('Monitoring offset logic lead') }}</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 cabinet-mon-offset-logic">
                <thead class="table-light">
                <tr>
                    <th>{{ __('Monitoring offset logic col case') }}</th>
                    <th>{{ __('Monitoring offset logic col action') }}</th>
                </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="small">{{ __('Monitoring offset logic case in range') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring offset logic action in range') }}</td>
                    </tr>
                    <tr>
                        <td class="small">{{ __('Monitoring offset logic case minus') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring offset logic action minus') }}</td>
                    </tr>
                    <tr>
                        <td class="small">{{ __('Monitoring offset logic case floor') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring offset logic action floor') }}</td>
                    </tr>
                    <tr>
                        <td class="small">{{ __('Monitoring offset logic case multi') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring offset logic action multi') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
