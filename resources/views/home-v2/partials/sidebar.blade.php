<div class="card cabinet-home-v2-account-card shadow-sm mb-3">
    <div class="card-body">
        <p class="small text-uppercase mb-1 opacity-75">{{ __('Welcome') }}</p>
        <h2 class="h5 mb-3 text-break">{{ $summary['displayName'] }}</h2>
        <div class="d-flex flex-wrap gap-2 small mb-0">
            <span class="badge rounded-pill text-bg-light text-dark">
                <i class="bi bi-wallet2 me-1"></i>{{ $summary['balanceFormatted'] }} ₽
            </span>
            @if($summary['tariffName'])
                <span class="badge rounded-pill text-bg-light text-dark">
                    <i class="bi bi-tag me-1"></i>{{ $summary['tariffName'] }}
                </span>
            @endif
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">{{ __('Quick access') }}</h3>
    </div>
    <div class="card-body d-grid gap-2 cabinet-home-v2-quick p-2">
        <a href="{{ route('balance.index') }}" class="btn btn-outline-success btn-sm">
            <i class="bi bi-wallet2 me-2"></i>{{ __('Top up your balance') }}
        </a>
        <a href="{{ route('tariff.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-tag me-2"></i>{{ __('Tariffs') }}
        </a>
        <a href="{{ route('support.index', array_filter(['status' => $summary['supportFilter']])) }}"
           class="btn btn-outline-info btn-sm">
            <i class="bi bi-headset me-2"></i>{{ __('Support') }}
            @if($summary['supportCount'] > 0)
                <span class="badge text-bg-danger ms-1">{{ $summary['supportCount'] }}</span>
            @endif
        </a>
        <a href="{{ route('news') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-newspaper me-2"></i>{{ __('News and updates') }}
            @if($summary['newsCount'] > 0)
                <span class="badge text-bg-warning ms-1">{{ $summary['newsCount'] }}</span>
            @endif
        </a>
        @unless($summary['telegramConnected'])
            <a href="{{ route('profile.index') }}#telegram" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-telegram me-2"></i>{{ __('Connect Telegram bot') }}
            </a>
        @endunless
        <a href="{{ route('profile.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person me-2"></i>{{ __('Profile') }}
        </a>
        <a href="{{ route('menu.config') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-sliders me-2"></i>{{ __('Setting menu') }}
        </a>
    </div>
</div>

<div class="row g-2">
    <div class="col-12">
        <a href="{{ route('balance.index') }}" class="cabinet-home-v2-stat-pill w-100">
            <span class="cabinet-home-v2-stat-pill__icon text-bg-success"><i class="bi bi-wallet2"></i></span>
            <span>
                <span class="d-block small text-secondary">{{ __('Your balance') }}</span>
                <span class="fw-semibold">{{ $summary['balanceFormatted'] }} ₽</span>
            </span>
        </a>
    </div>
    <div class="col-12">
        <a href="{{ route('support.index', array_filter(['status' => $summary['supportFilter']])) }}"
           class="cabinet-home-v2-stat-pill w-100">
            <span class="cabinet-home-v2-stat-pill__icon text-bg-info"><i class="bi bi-headset"></i></span>
            <span>
                <span class="d-block small text-secondary">{{ __('Support') }}</span>
                <span class="fw-semibold">
                    @if($summary['supportCount'] > 0)
                        {{ __('New messages') }}: {{ $summary['supportCount'] }}
                    @else
                        {{ __('No new messages') }}
                    @endif
                </span>
            </span>
        </a>
    </div>
</div>
