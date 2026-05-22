<div class="card card-outline card-success h-100">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="bi bi-check-circle me-1"></i>{{ __('Active subscription') }}
        </h3>
    </div>
    <div class="card-body">
        <p class="text-secondary small mb-3">{{ __('Tariff plan you are subscribed to.') }}</p>
        @include('tariff.partials._table', ['id' => 'subscription-info', 'total' => $actual['info']])
    </div>
    <div class="card-footer">
        <a href="javascript:void(0)" class="btn btn-outline-danger w-100" id="unsubscribe">
            <i class="bi bi-x-circle me-1"></i>{{ __('Cancel subscription') }}
        </a>
    </div>
</div>
