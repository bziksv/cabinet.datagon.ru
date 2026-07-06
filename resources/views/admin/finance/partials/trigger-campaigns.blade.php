<div class="card card-outline card-info shadow-sm mb-4 cabinet-finance-trigger-card" id="cabinet-finance-trigger-panel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="bi bi-envelope-paper-heart me-1"></i>{{ __('Trigger campaigns title') }}
        </h3>
        <div class="card-tools d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-secondary">{{ __('Trigger campaigns channel cvng') }}</span>
            <button type="button"
                    id="cabinet-finance-trigger-toggle"
                    class="btn btn-sm btn-outline-secondary cabinet-finance-trigger-toggle"
                    data-bs-toggle="collapse"
                    data-bs-target="#cabinet-finance-trigger-collapse"
                    aria-expanded="true"
                    aria-controls="cabinet-finance-trigger-collapse"
                    title="{{ __('Finance credit collapse') }}">
                <span class="cabinet-finance-trigger-toggle__label">{{ __('Finance credit collapse') }}</span>
                <i class="bi bi-chevron-up cabinet-finance-trigger-toggle__icon" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div id="cabinet-finance-trigger-collapse" class="collapse show">
        <div class="card-body border-top">
            <p class="text-secondary small mb-3">{{ __('Trigger campaigns lead') }}</p>

            @foreach($triggerCampaigns as $campaign)
                @php
                    $stats = $triggerStats[$campaign->id] ?? ['pending' => 0, 'sent' => 0, 'redeemed' => 0, 'failed' => 0, 'audience' => 0, 'conversion' => 0];
                @endphp
                <div class="card shadow-sm mb-3 cabinet-finance-trigger-campaign-card {{ $campaign->isRunning() ? 'border-success' : ($campaign->isPaused() ? 'border-warning' : '') }}"
                     id="trigger-campaign-{{ $campaign->id }}">
                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="pe-2">
                            <strong>{{ $campaign->name }}</strong>
                            <span class="small text-secondary d-block">{{ $campaign->description }}</span>
                            <span class="badge text-bg-light text-dark border mt-1">
                                @if($campaign->isTariffExpiringTrigger())
                                    {{ __('Trigger type tariff expiring') }}
                                @elseif($campaign->isTariffExpiredTrigger())
                                    {{ __('Trigger type tariff expired') }}
                                @elseif($campaign->isRegisteredNoTopupTrigger())
                                    {{ __('Trigger type registered no topup') }}
                                @elseif($campaign->isRegisteredNoToolTrigger())
                                    {{ __('Trigger type registered no tool') }}
                                @else
                                    {{ __('Trigger type inactive days') }}
                                @endif
                            </span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                            @if($campaign->isRunning())
                                <span class="badge text-bg-success">{{ __('Trigger campaign running') }}</span>
                            @elseif($campaign->isPaused())
                                <span class="badge text-bg-warning">{{ __('Trigger campaign paused') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('Trigger campaign inactive') }}</span>
                            @endif

                            @if(!$campaign->is_active)
                                <form method="post" action="{{ route('admin.finance.trigger.toggle', $campaign) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-play-fill me-1"></i>{{ __('Trigger campaign activate') }}
                                    </button>
                                </form>
                            @else
                                @if($campaign->isPaused())
                                    <form method="post" action="{{ route('admin.finance.trigger.resume', $campaign) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-play-fill me-1"></i>{{ __('Trigger campaign resume') }}
                                        </button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('admin.finance.trigger.pause', $campaign) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pause-fill me-1"></i>{{ __('Trigger campaign pause') }}
                                        </button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('admin.finance.trigger.toggle', $campaign) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-stop-fill me-1"></i>{{ __('Trigger campaign deactivate') }}
                                    </button>
                                </form>
                            @endif

                            <form method="post" action="{{ route('admin.finance.trigger.test', $campaign) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="lang" value="ru">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-send me-1"></i>{{ __('Trigger campaign test send ru') }}
                                </button>
                            </form>
                            <form method="post" action="{{ route('admin.finance.trigger.test', $campaign) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="lang" value="en">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-send me-1"></i>{{ __('Trigger campaign test send en') }}
                                </button>
                            </form>

                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary cabinet-finance-trigger-campaign-toggle"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#trigger-campaign-collapse-{{ $campaign->id }}"
                                    data-trigger-campaign-id="{{ $campaign->id }}"
                                    aria-expanded="true"
                                    aria-controls="trigger-campaign-collapse-{{ $campaign->id }}"
                                    title="{{ __('Finance credit collapse') }}">
                                <span class="cabinet-finance-trigger-campaign-toggle__label">{{ __('Finance credit collapse') }}</span>
                                <i class="bi bi-chevron-up cabinet-finance-trigger-campaign-toggle__icon" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div id="trigger-campaign-collapse-{{ $campaign->id }}" class="collapse show">
                    <div class="card-body border-top">
                        <div class="cabinet-finance-trigger-stats mb-4">
                            <div class="cabinet-finance-trigger-stat">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat audience') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value">{{ number_format($stats['audience'], 0, '.', ' ') }}</strong>
                                <span class="cabinet-finance-trigger-stat__spacer" aria-hidden="true"></span>
                            </div>
                            <a href="{{ route('admin.finance.trigger.stats', [$campaign, 'filter' => 'sent']) }}"
                               class="cabinet-finance-trigger-stat cabinet-finance-trigger-stat--link text-decoration-none">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat sent') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value">{{ number_format($stats['sent'], 0, '.', ' ') }}</strong>
                                <span class="cabinet-finance-trigger-stat__hint">{{ __('Trigger stat click details') }}</span>
                            </a>
                            <a href="{{ route('admin.finance.trigger.stats', [$campaign, 'filter' => 'opened']) }}"
                               class="cabinet-finance-trigger-stat cabinet-finance-trigger-stat--link text-decoration-none">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat opened') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value text-primary">{{ number_format($stats['opened'] ?? 0, 0, '.', ' ') }}</strong>
                                <span class="cabinet-finance-trigger-stat__meta">{{ $stats['open_rate'] ?? 0 }}%</span>
                            </a>
                            <a href="{{ route('admin.finance.trigger.stats', [$campaign, 'filter' => 'redeemed']) }}"
                               class="cabinet-finance-trigger-stat cabinet-finance-trigger-stat--link text-decoration-none">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat redeemed') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value text-success">{{ number_format($stats['redeemed'], 0, '.', ' ') }}</strong>
                                <span class="cabinet-finance-trigger-stat__meta">{{ $stats['conversion'] }}%</span>
                            </a>
                            <div class="cabinet-finance-trigger-stat">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat conversion') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value">{{ $stats['conversion'] }}%</strong>
                                <span class="cabinet-finance-trigger-stat__spacer" aria-hidden="true"></span>
                            </div>
                            <div class="cabinet-finance-trigger-stat">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat pending') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value">{{ number_format($stats['pending'], 0, '.', ' ') }}</strong>
                                <span class="cabinet-finance-trigger-stat__spacer" aria-hidden="true"></span>
                            </div>
                            <div class="cabinet-finance-trigger-stat">
                                <span class="cabinet-finance-trigger-stat__label">{{ __('Trigger stat failed') }}</span>
                                <strong class="cabinet-finance-trigger-stat__value text-danger">{{ number_format($stats['failed'], 0, '.', ' ') }}</strong>
                                <span class="cabinet-finance-trigger-stat__spacer" aria-hidden="true"></span>
                            </div>
                        </div>

                        <form method="post" action="{{ route('admin.finance.trigger.update', $campaign) }}" class="cabinet-finance-trigger-form">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="trigger-name-{{ $campaign->id }}">{{ __('Trigger field name') }}</label>
                                    <input type="text" name="name" id="trigger-name-{{ $campaign->id }}" class="form-control" value="{{ $campaign->name }}" required>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label" for="trigger-days-{{ $campaign->id }}">{{ __($campaign->triggerDaysLabelKey()) }}</label>
                                    <input type="number" name="trigger_days" id="trigger-days-{{ $campaign->id }}" class="form-control" min="1" value="{{ $campaign->trigger_days }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-desc-{{ $campaign->id }}">{{ __('Trigger field description') }}</label>
                                    <textarea name="description" id="trigger-desc-{{ $campaign->id }}" class="form-control" rows="2">{{ $campaign->description }}</textarea>
                                </div>
                                @if($campaign->sendsPromo())
                                <div class="col-6 col-md-4">
                                    <label class="form-label" for="trigger-bonus-type-{{ $campaign->id }}">{{ __('Trigger field coupon bonus type') }}</label>
                                    <select name="coupon_bonus_type" id="trigger-bonus-type-{{ $campaign->id }}" class="form-select">
                                        <option value="fixed" {{ $campaign->couponBonusType() === 'fixed' ? 'selected' : '' }}>{{ __('Promo bonus fixed') }}</option>
                                        <option value="percent" {{ $campaign->couponBonusType() === 'percent' ? 'selected' : '' }}>{{ __('Promo bonus percent') }}</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label" for="trigger-bonus-{{ $campaign->id }}">{{ __('Trigger field coupon bonus') }}</label>
                                    <div class="input-group">
                                        <input type="number"
                                               name="coupon_bonus_value"
                                               id="trigger-bonus-{{ $campaign->id }}"
                                               class="form-control"
                                               min="1"
                                               max="{{ $campaign->isPercentCoupon() ? 100 : 10000000 }}"
                                               value="{{ $campaign->coupon_bonus_value }}"
                                               required>
                                        <span class="input-group-text">{{ $campaign->isPercentCoupon() ? '%' : '₽' }}</span>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label" for="trigger-expires-{{ $campaign->id }}">{{ __('Trigger field coupon expires') }}</label>
                                    <div class="input-group">
                                        <input type="number" name="coupon_expires_days" id="trigger-expires-{{ $campaign->id }}" class="form-control" min="1" value="{{ $campaign->coupon_expires_days }}" required>
                                        <span class="input-group-text">{{ __('days') }}</span>
                                    </div>
                                </div>
                                @else
                                <div class="col-12">
                                    <p class="form-text mb-0">{{ __('Trigger field no promo note') }}</p>
                                </div>
                                @endif
                                <div class="col-6 col-md-4">
                                    <label class="form-label" for="trigger-send-rate-{{ $campaign->id }}">{{ __('Trigger field send rate per minute') }}</label>
                                    <div class="input-group">
                                        <input type="number"
                                               name="send_rate_per_minute"
                                               id="trigger-send-rate-{{ $campaign->id }}"
                                               class="form-control"
                                               min="1"
                                               max="{{ \App\TriggerCampaign::MAX_SEND_RATE_PER_MINUTE }}"
                                               value="{{ $campaign->send_rate_per_minute ?: \App\TriggerCampaign::DEFAULT_SEND_RATE_PER_MINUTE }}"
                                               required>
                                        <span class="input-group-text">{{ __('Trigger field send rate per minute suffix') }}</span>
                                    </div>
                                    <p class="form-text mb-0">{{ __('Trigger field send rate per minute hint') }}</p>
                                </div>
                                <div class="col-12">
                                    <h5 class="h6 mb-1">{{ __('Trigger field email section') }}</h5>
                                    <p class="form-text mb-2">{{ __('Trigger field email english hint') }}</p>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-subject-{{ $campaign->id }}">{{ __('Trigger field email subject') }}</label>
                                    <input type="text" name="email_subject" id="trigger-subject-{{ $campaign->id }}" class="form-control" value="{{ $campaign->email_subject }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-subject-en-{{ $campaign->id }}">{{ __('Trigger field email subject en') }}</label>
                                    <input type="text" name="email_subject_en" id="trigger-subject-en-{{ $campaign->id }}" class="form-control cabinet-finance-trigger-field-en" value="{{ $campaign->email_subject_en }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-intro-{{ $campaign->id }}">{{ __('Trigger field email intro') }}</label>
                                    <textarea name="email_intro" id="trigger-intro-{{ $campaign->id }}" class="form-control" rows="2">{{ $campaign->email_intro }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-intro-en-{{ $campaign->id }}">{{ __('Trigger field email intro en') }}</label>
                                    <textarea name="email_intro_en" id="trigger-intro-en-{{ $campaign->id }}" class="form-control cabinet-finance-trigger-field-en" rows="2">{{ $campaign->email_intro_en }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-body-{{ $campaign->id }}">{{ __('Trigger field email body') }}</label>
                                    <textarea name="email_body" id="trigger-body-{{ $campaign->id }}" class="form-control" rows="4">{{ $campaign->email_body }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="trigger-body-en-{{ $campaign->id }}">{{ __('Trigger field email body en') }}</label>
                                    <textarea name="email_body_en" id="trigger-body-en-{{ $campaign->id }}" class="form-control cabinet-finance-trigger-field-en" rows="4">{{ $campaign->email_body_en }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check-lg me-1"></i>{{ __('Trigger campaign save') }}
                                    </button>
                                    <span class="small text-secondary ms-2">{{ __('Trigger campaign autosend note') }}</span>
                                </div>
                            </div>
                        </form>
                    </div>
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</div>
