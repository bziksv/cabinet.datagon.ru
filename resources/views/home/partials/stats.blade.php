<div class="row g-3 mb-4 cabinet-home-stats align-items-stretch">
    <div class="col-12 col-sm-6 col-xl-3 d-flex">
        <a href="{{ route('balance.index') }}" class="info-box mb-0 flex-fill">
            <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-wallet2"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Your balance') }}</span>
                <span class="info-box-number">{{ $summary['balanceFormatted'] }} ₽</span>
                <span class="info-box-meta text-secondary">{{ __('Top up your balance') }}</span>
            </div>
        </a>
    </div>

    <div class="col-12 col-sm-6 col-xl-3 d-flex">
        <a href="{{ route('tariff.index') }}" class="info-box mb-0 flex-fill">
            <span class="info-box-icon text-bg-primary shadow-sm">
                <i class="bi bi-tag"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Your tariff') }}</span>
                <span class="info-box-number text-truncate">
                    {{ $summary['tariffName'] ?: '—' }}
                </span>
                <span class="info-box-meta text-secondary">{{ __('Tariffs') }}</span>
            </div>
        </a>
    </div>

    <div class="col-12 col-sm-6 col-xl-3 d-flex">
        <a href="{{ route('support.index', array_filter(['status' => $summary['supportFilter']])) }}"
           class="info-box mb-0 flex-fill">
            <span class="info-box-icon text-bg-info shadow-sm">
                <i class="bi bi-headset"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Support') }}</span>
                <span class="info-box-number">
                    @if($summary['supportCount'] > 0)
                        {{ $summary['supportCount'] }}
                        <span class="badge text-bg-danger ms-1">{{ __('New') }}</span>
                    @else
                        {{ __('No new messages') }}
                    @endif
                </span>
                <span class="info-box-meta text-secondary">{{ __('Support service') }}</span>
            </div>
        </a>
    </div>

    <div class="col-12 col-sm-6 col-xl-3 d-flex">
        @if($summary['newsCount'] > 0)
            <a href="{{ route('news') }}" class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-warning shadow-sm">
                    <i class="bi bi-newspaper"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('News and updates') }}</span>
                    <span class="info-box-number">{{ $summary['newsCount'] }}</span>
                    <span class="info-box-meta text-secondary">{{ __('Unread') }}</span>
                </div>
            </a>
        @elseif(!$summary['telegramConnected'])
            <a href="{{ route('profile.index') }}#telegram" class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-secondary shadow-sm">
                    <i class="bi bi-telegram"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Telegram bot') }}</span>
                    <span class="info-box-number" style="font-size: 1rem;">{{ __('Connect') }}</span>
                    <span class="info-box-meta text-secondary">{{ __('Project notifications') }}</span>
                </div>
            </a>
        @else
            <a href="{{ route('news') }}" class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-secondary shadow-sm">
                    <i class="bi bi-newspaper"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('News and updates') }}</span>
                    <span class="info-box-number" style="font-size: 1rem;">{{ __('All read') }}</span>
                    <span class="info-box-meta text-secondary">{{ __('Stay up to date') }}</span>
                </div>
            </a>
        @endif
    </div>
</div>
