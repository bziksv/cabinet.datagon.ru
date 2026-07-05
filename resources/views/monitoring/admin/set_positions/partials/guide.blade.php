<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="callout callout-info h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Monitoring set pos guide what') }}</h5>
                    <p class="small mb-0">{{ __('Monitoring set pos guide what body') }}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-warning h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Monitoring set pos guide caution') }}</h5>
                    <p class="small mb-0">{{ __('Monitoring set pos guide caution body') }}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-success h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-list-ol me-1"></i>{{ __('Monitoring set pos guide steps') }}</h5>
                    <ol class="small mb-0 ps-3">
                        <li>{{ __('Monitoring set pos guide step project') }}</li>
                        <li>{{ __('Monitoring set pos guide step engine') }}</li>
                        <li>{{ __('Monitoring set pos guide step dates') }}</li>
                        <li>{{ __('Monitoring set pos guide step run') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white py-3">
        <h3 class="h6 mb-1">{{ __('Monitoring set pos logic title') }}</h3>
        <p class="small text-secondary mb-0">{{ __('Monitoring set pos logic lead') }}</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 cabinet-mon-set-pos-logic">
                <thead class="table-light">
                <tr>
                    <th>{{ __('Monitoring set pos logic col case') }}</th>
                    <th>{{ __('Monitoring set pos logic col action') }}</th>
                </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="small">{{ __('Monitoring set pos logic case empty') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring set pos logic action empty') }}</td>
                    </tr>
                    <tr>
                        <td class="small">{{ __('Monitoring set pos logic case exists') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring set pos logic action exists') }}</td>
                    </tr>
                    <tr>
                        <td class="small">{{ __('Monitoring set pos logic case value') }}</td>
                        <td class="small text-secondary">{{ __('Monitoring set pos logic action value') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
