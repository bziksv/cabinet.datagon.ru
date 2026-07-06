@php
    $stats = $row['dispatch_stats'] ?? [];
    $periods = [
        'today' => __('Users notify dispatch today'),
        'yesterday' => __('Users notify dispatch yesterday'),
        'month' => __('Users notify dispatch month'),
    ];
@endphp
<div class="cabinet-notify-dispatch-stats">
    @foreach($periods as $periodKey => $periodLabel)
        @php
            $period = $stats[$periodKey] ?? ['telegram' => 0, 'email' => 0];
            $tg = (int) ($period['telegram'] ?? 0);
            $email = (int) ($period['email'] ?? 0);
        @endphp
        <div class="cabinet-notify-dispatch-row">
            <span class="cabinet-notify-dispatch-period">{{ $periodLabel }}</span>
            <span class="cabinet-notify-dispatch-channels">
                <span class="cabinet-notify-dispatch-channel text-info" title="Telegram">
                    <i class="bi bi-telegram" aria-hidden="true"></i>{{ number_format($tg, 0, ',', ' ') }}
                </span>
                <span class="cabinet-notify-dispatch-channel text-cabinet-email" title="{{ __('Email') }}">
                    <i class="bi bi-envelope" aria-hidden="true"></i>{{ number_format($email, 0, ',', ' ') }}
                </span>
            </span>
        </div>
    @endforeach
</div>
