<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="callout callout-info h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-info-circle me-1"></i>{{ __('Monitoring perm guide what') }}</h5>
                    <p class="small mb-0">{{ __('Monitoring perm guide what body') }}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-warning h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Monitoring perm guide not global') }}</h5>
                    <p class="small mb-0">{!! __('Monitoring perm guide not global body') !!}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-success h-100 mb-0">
                    <h5 class="h6 mb-2"><i class="bi bi-toggle-on me-1"></i>{{ __('Monitoring perm guide how save') }}</h5>
                    <p class="small mb-0">{{ __('Monitoring perm guide how save body') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white py-3">
        <h3 class="h6 mb-1">{{ __('Monitoring perm roles overview title') }}</h3>
        <p class="small text-secondary mb-0">{{ __('Monitoring perm roles overview lead') }}</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 cabinet-mon-perm-overview">
                <thead class="table-light">
                <tr>
                    <th>{{ __('Monitoring perm overview col role') }}</th>
                    <th>{{ __('Monitoring perm overview col who') }}</th>
                    <th class="text-end">{{ __('Monitoring perm overview col enabled') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($roleStats as $stat)
                    @php($meta = $roleMeta[$stat['role']->name] ?? null)
                    <tr>
                        <td>
                            @if($meta)
                                <span class="badge text-bg-{{ $meta['badge'] }} me-1">{{ __($meta['title_key']) }}</span>
                            @else
                                <span class="badge text-bg-secondary me-1">{{ $stat['role']->title ?: $stat['role']->name }}</span>
                            @endif
                            <code class="small text-secondary">{{ $stat['role']->name }}</code>
                        </td>
                        <td class="small text-secondary">
                            {{ $meta ? __($meta['who_key']) : '—' }}
                        </td>
                        <td class="text-end text-nowrap">
                            <span class="fw-semibold">{{ $stat['enabled'] }}</span>
                            <span class="text-secondary">/ {{ $stat['total'] }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
