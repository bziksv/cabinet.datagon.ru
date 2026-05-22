<div class="card shadow-sm mb-3 cabinet-home-v3-kpi">
    <div class="row g-0">
        <div class="col-6 col-md-3">
            <a href="{{ route('balance.index') }}" class="kpi-cell">
                <div class="kpi-label">{{ __('Your balance') }}</div>
                <div class="kpi-value text-success">{{ $summary['balanceFormatted'] }} ₽</div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('tariff.index') }}" class="kpi-cell">
                <div class="kpi-label">{{ __('Your tariff') }}</div>
                <div class="kpi-value text-primary text-truncate">
                    {{ $summary['tariffName'] ?: '—' }}
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('support.index', array_filter(['status' => $summary['supportFilter']])) }}" class="kpi-cell">
                <div class="kpi-label">{{ __('Support') }}</div>
                <div class="kpi-value text-info">
                    @if($summary['supportCount'] > 0)
                        {{ $summary['supportCount'] }}
                        <span class="badge text-bg-danger align-middle ms-1">{{ __('New') }}</span>
                    @else
                        0
                    @endif
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            @if($summary['newsCount'] > 0)
                <a href="{{ route('news') }}" class="kpi-cell">
                    <div class="kpi-label">{{ __('News') }}</div>
                    <div class="kpi-value text-warning">{{ $summary['newsCount'] }}</div>
                </a>
            @elseif(!$summary['telegramConnected'])
                <a href="{{ route('profile.index') }}#telegram" class="kpi-cell">
                    <div class="kpi-label">{{ __('Telegram') }}</div>
                    <div class="kpi-value" style="font-size: 1rem;">{{ __('Connect') }}</div>
                </a>
            @else
                <a href="{{ route('news') }}" class="kpi-cell">
                    <div class="kpi-label">{{ __('News') }}</div>
                    <div class="kpi-value text-secondary" style="font-size: 1rem;">OK</div>
                </a>
            @endif
        </div>
    </div>
</div>
