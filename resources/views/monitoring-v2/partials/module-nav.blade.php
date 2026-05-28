<nav class="cabinet-mon-v2-module-nav card border shadow-sm" aria-label="{{ __('Monitoring v2 nav label') }}">
    <div class="card-body py-2 px-3">
        <ul class="nav nav-pills flex-wrap gap-1 mb-0">
            <li class="nav-item">
                <span class="nav-link active disabled" aria-current="page">
                    <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>{{ __('Projects') }}
                </span>
            </li>
            @if($isMonitoringAdmin ?? false)
                <li class="nav-item ms-md-auto">
                    <a class="nav-link" href="{{ route('monitoring.admin') }}">{{ __('Administration') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('monitoring.stat') }}">{{ __('Statistics') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('monitoring-permissions.index') }}">{{ __('Права') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('set.positions') }}">{{ __('Set positions') }}</a>
                </li>
            @endif
        </ul>
    </div>
</nav>
