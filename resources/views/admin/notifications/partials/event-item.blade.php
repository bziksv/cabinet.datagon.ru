@php
    $event = $event ?? [];
    $channelStyles = [
        'telegram' => ['class' => 'tg', 'icon' => 'bi-telegram'],
        'email' => ['class' => 'mail', 'icon' => 'bi-envelope'],
        'modal' => ['class' => 'modal', 'icon' => 'bi-window'],
    ];
@endphp
<article class="card card-outline card-secondary shadow-sm cabinet-notify-event mb-3" id="notify-event-{{ $event['id'] ?? '' }}">
    <div class="card-header py-2">
        <h4 class="card-title h6 mb-0 d-flex align-items-center gap-2">
            <span class="cabinet-notify-event__dot" aria-hidden="true"></span>
            {{ $event['title'] ?? '' }}
        </h4>
    </div>
    <div class="card-body pt-3 pb-3">
        <div class="row g-2 g-md-3 mb-3 cabinet-notify-event__tiles">
            <div class="col-md-4">
                <div class="cabinet-notify-tile h-100">
                    <div class="cabinet-notify-tile__label">
                        <i class="bi bi-lightning-charge text-warning" aria-hidden="true"></i>
                        {{ __('Users notify when') }}
                    </div>
                    <div class="cabinet-notify-tile__text">{{ $event['trigger'] ?? '' }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="cabinet-notify-tile h-100">
                    <div class="cabinet-notify-tile__label">
                        <i class="bi bi-person-check text-primary" aria-hidden="true"></i>
                        {{ __('Users notify who') }}
                    </div>
                    <div class="cabinet-notify-tile__text">{{ $event['audience'] ?? '' }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="cabinet-notify-tile h-100">
                    <div class="cabinet-notify-tile__label">
                        <i class="bi bi-tag text-success" aria-hidden="true"></i>
                        {{ __('Users notify tariff row') }}
                    </div>
                    <div class="cabinet-notify-tile__text">{{ $event['tariff'] ?? '' }}</div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small text-secondary me-1">{{ __('Users notify channels row') }}:</span>
            @forelse($event['channel_badges'] ?? [] as $badge)
                <span class="badge text-bg-{{ $badge['color'] ?? 'secondary' }} cabinet-notify-channel-badge">
                    <i class="bi {{ $badge['icon'] ?? 'bi-bell' }} me-1" aria-hidden="true"></i>{{ $badge['label'] ?? '' }}
                </span>
            @empty
                <span class="badge text-bg-light text-dark border">{{ __('Users notify channel in app only') }}</span>
            @endforelse
            @if(!empty($event['cron']))
                <span class="badge text-bg-light text-dark border font-monospace ms-md-auto">
                    <i class="bi bi-clock-history me-1" aria-hidden="true"></i>{{ $event['cron'] }}
                </span>
            @endif
        </div>

        @if(!empty($event['examples']))
            <div class="cabinet-notify-previews">
                <div class="small fw-semibold text-secondary mb-2">
                    <i class="bi bi-chat-square-text me-1" aria-hidden="true"></i>{{ __('Users notify examples title') }}
                </div>
                <div class="row g-2">
                    @foreach($event['examples'] as $example)
                        @php
                            $style = $channelStyles[$example['type'] ?? ''] ?? ['class' => 'default', 'icon' => 'bi-bell'];
                        @endphp
                        <div class="col-lg-6">
                            <div class="cabinet-notify-preview cabinet-notify-preview--{{ $style['class'] }}">
                                <div class="cabinet-notify-preview__head">
                                    <i class="bi {{ $style['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $example['label'] ?? '' }}</span>
                                </div>
                                <div class="cabinet-notify-preview__body">{{ $example['text'] ?? '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($event['code_ref']))
            <details class="cabinet-notify-event__dev small mt-3">
                <summary class="text-secondary">
                    <i class="bi bi-code-slash me-1" aria-hidden="true"></i>{{ __('Users notify code ref') }}
                </summary>
                <code class="d-block mt-2 p-2 rounded user-select-all">{{ $event['code_ref'] }}</code>
            </details>
        @endif
    </div>
</article>
