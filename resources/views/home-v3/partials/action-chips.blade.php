<div class="card shadow-sm mt-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-secondary me-1">{{ __('Also') }}:</span>
            <a href="{{ route('profile.index') }}" class="cabinet-home-v3-action-chip">
                <i class="bi bi-person"></i>{{ __('Profile') }}
            </a>
            <a href="{{ route('menu.config') }}" class="cabinet-home-v3-action-chip">
                <i class="bi bi-sliders"></i>{{ __('Setting menu') }}
            </a>
            <a href="{{ route('balance.index') }}" class="cabinet-home-v3-action-chip">
                <i class="bi bi-wallet2"></i>{{ __('Top up your balance') }}
            </a>
            <a href="{{ route('support.create') }}" class="cabinet-home-v3-action-chip">
                <i class="bi bi-plus-circle"></i>{{ __('New ticket') }}
            </a>
        </div>
    </div>
</div>
