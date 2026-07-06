<div class="card card-outline card-warning shadow-sm mb-4 cabinet-finance-promo-card" id="cabinet-finance-promo-panel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">
            <i class="bi bi-ticket-perforated me-1"></i>{{ __('Promo codes title') }}
        </h3>
        <div class="card-tools">
            <button type="button"
                    id="cabinet-finance-promo-toggle"
                    class="btn btn-sm btn-outline-secondary cabinet-finance-promo-toggle"
                    data-bs-toggle="collapse"
                    data-bs-target="#cabinet-finance-promo-collapse"
                    aria-expanded="true"
                    aria-controls="cabinet-finance-promo-collapse"
                    title="{{ __('Finance credit collapse') }}">
                <span class="cabinet-finance-promo-toggle__label">{{ __('Finance credit collapse') }}</span>
                <i class="bi bi-chevron-up cabinet-finance-promo-toggle__icon" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <div id="cabinet-finance-promo-collapse" class="collapse show">
        <div class="card-body border-top">
            <p class="text-secondary small mb-3">{{ __('Promo codes lead') }}</p>

            <div class="row g-4">
                <div class="col-12 col-xl-5">
                    <h4 class="h6 mb-3">{{ __('Promo code create title') }}</h4>
                    <form method="post" action="{{ route('admin.finance.promo.store') }}" class="cabinet-finance-promo-form">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="promo-code">{{ __('Promo code field code') }}</label>
                            <input type="text" name="code" id="promo-code" class="form-control" maxlength="64" required placeholder="WELCOME500">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="promo-title">{{ __('Promo code field title') }}</label>
                            <input type="text" name="title" id="promo-title" class="form-control" maxlength="120" placeholder="{{ __('Promo code field title placeholder') }}">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="promo-bonus-type">{{ __('Promo code field bonus type') }}</label>
                                <select name="bonus_type" id="promo-bonus-type" class="form-select">
                                    <option value="fixed">{{ __('Promo bonus fixed') }}</option>
                                    <option value="percent">{{ __('Promo bonus percent') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="promo-bonus-value">{{ __('Promo code field bonus value') }}</label>
                                <input type="number" name="bonus_value" id="promo-bonus-value" class="form-control" min="1" required value="500">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="promo-usage-mode">{{ __('Promo code field usage') }}</label>
                                <select name="usage_mode" id="promo-usage-mode" class="form-select">
                                    <option value="once">{{ __('Promo usage once') }}</option>
                                    <option value="multi">{{ __('Promo usage multi') }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="promo-redeem-mode">{{ __('Promo code field redeem mode') }}</label>
                                <select name="redeem_mode" id="promo-redeem-mode" class="form-select">
                                    <option value="topup_bonus">{{ __('Promo redeem topup bonus') }}</option>
                                    <option value="standalone_credit">{{ __('Promo redeem standalone') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-12">
                                <label class="form-label" for="promo-max-uses">{{ __('Promo code field max uses') }}</label>
                                <input type="number" name="max_uses" id="promo-max-uses" class="form-control" min="1" placeholder="{{ __('Promo code field max uses placeholder') }}">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="promo-starts-at">{{ __('Promo code field starts') }}</label>
                                <input type="datetime-local" name="starts_at" id="promo-starts-at" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="promo-expires-at">{{ __('Promo code field expires') }}</label>
                                <input type="datetime-local" name="expires_at" id="promo-expires-at" class="form-control">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="promo-is-active" checked>
                            <label class="form-check-label" for="promo-is-active">{{ __('Promo code field active') }}</label>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-plus-lg me-1"></i>{{ __('Promo code create submit') }}
                        </button>
                    </form>

                    <hr class="my-4">

                    <h4 class="h6 mb-3">{{ __('Promo simulate title') }}</h4>
                    <p class="text-secondary small">{{ __('Promo simulate lead') }}</p>
                    <form method="post" action="{{ route('admin.finance.promo.simulate') }}" id="cabinet-finance-promo-simulate-form">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="promo-simulate-user">{{ __('Finance credit user') }}</label>
                            <select name="user_id" id="promo-simulate-user" class="form-select" required data-placeholder="{{ __('Finance credit user placeholder') }}">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="promo-simulate-sum">{{ __('Sum') }}</label>
                                <input type="number" name="sum" id="promo-simulate-sum" class="form-control" min="1" value="1000" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="promo-simulate-code">{{ __('Promo code field code') }}</label>
                                <input type="text" name="promo_code" id="promo-simulate-code" class="form-control" maxlength="64" placeholder="WELCOME500">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bi bi-play-circle me-1"></i>{{ __('Promo simulate submit') }}
                        </button>
                    </form>
                </div>

                <div class="col-12 col-xl-7">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h4 class="h6 mb-0">{{ __('Promo codes list title') }}</h4>
                        @if($promoCodes->total() > 0)
                            <span class="text-secondary small">
                                {{ __('Showing') }}
                                <strong>{{ $promoCodes->firstItem() }}–{{ $promoCodes->lastItem() }}</strong>
                                {{ __('of') }}
                                <strong>{{ number_format($promoCodes->total(), 0, '.', ' ') }}</strong>
                            </span>
                        @endif
                    </div>

                    <form method="get" action="{{ route('admin.finance.index') }}" class="row g-2 align-items-end mb-3">
                        <input type="hidden" name="tab" value="promo">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="promo-filter-q">{{ __('Promo codes filter search') }}</label>
                            <input type="search"
                                   name="promo_q"
                                   id="promo-filter-q"
                                   class="form-control form-control-sm"
                                   value="{{ $promoFilters['promo_q'] ?? '' }}"
                                   placeholder="{{ __('Promo codes filter search placeholder') }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="promo-filter-status">{{ __('Status') }}</label>
                            <select name="promo_status" id="promo-filter-status" class="form-select form-select-sm">
                                <option value="all" {{ ($promoFilters['promo_status'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('All') }}</option>
                                <option value="active" {{ ($promoFilters['promo_status'] ?? '') === 'active' ? 'selected' : '' }}>{{ __('Promo codes filter active') }}</option>
                                <option value="inactive" {{ ($promoFilters['promo_status'] ?? '') === 'inactive' ? 'selected' : '' }}>{{ __('Promo codes filter inactive') }}</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="promo-filter-source">{{ __('Promo codes filter source') }}</label>
                            <select name="promo_source" id="promo-filter-source" class="form-select form-select-sm">
                                <option value="all" {{ ($promoFilters['promo_source'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('All') }}</option>
                                <option value="manual" {{ ($promoFilters['promo_source'] ?? '') === 'manual' ? 'selected' : '' }}>{{ __('Promo codes filter manual') }}</option>
                                <option value="trigger" {{ ($promoFilters['promo_source'] ?? '') === 'trigger' ? 'selected' : '' }}>{{ __('Promo codes filter trigger') }}</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-funnel me-1"></i>{{ __('Apply') }}
                            </button>
                            <a href="{{ route('admin.finance.index', ['tab' => 'promo']) }}" class="btn btn-sm btn-outline-secondary">
                                {{ __('Reset') }}
                            </a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>{{ __('Promo code field code') }}</th>
                                <th>{{ __('Promo code field bonus') }}</th>
                                <th>{{ __('Promo code field redeem mode') }}</th>
                                <th>{{ __('Promo code field usage') }}</th>
                                <th>{{ __('Promo code field period') }}</th>
                                <th class="text-end">{{ __('Promo code field uses') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($promoCodes as $promo)
                                <tr class="{{ !$promo->is_active ? 'text-secondary' : '' }}">
                                    <td>
                                        <strong>{{ $promo->code }}</strong>
                                        @if($promo->title)
                                            <span class="small d-block">{{ $promo->title }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($promo->isFixed())
                                            +{{ number_format($promo->bonus_value, 0, '.', ' ') }} ₽
                                        @else
                                            +{{ $promo->bonus_value }}%
                                        @endif
                                    </td>
                                    <td>
                                        @if($promo->isStandaloneCredit())
                                            <span class="badge text-bg-info">{{ __('Promo redeem standalone short') }}</span>
                                        @else
                                            <span class="badge text-bg-light text-dark border">{{ __('Promo redeem topup short') }}</span>
                                        @endif
                                        @if($promo->assigned_user_id)
                                            <span class="small d-block text-secondary">#{{ $promo->assigned_user_id }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $promo->isOncePerUser() ? __('Promo usage once') : __('Promo usage multi') }}
                                        @if($promo->max_uses)
                                            <span class="small d-block">{{ __('Promo max uses short', ['count' => $promo->max_uses]) }}</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @if($promo->isPerpetual() && !$promo->starts_at)
                                            {{ __('Promo period perpetual') }}
                                        @else
                                            @if($promo->starts_at)
                                                {{ __('From') }} {{ $promo->starts_at->format('d.m.Y') }}<br>
                                            @endif
                                            @if($promo->expires_at)
                                                {{ __('To') }} {{ $promo->expires_at->format('d.m.Y') }}
                                            @else
                                                {{ __('Promo period no expiry') }}
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        {{ $promo->uses_count }}
                                        @if($promo->redemptions_count)
                                            <span class="small d-block text-secondary">{{ $promo->redemptions_count }} {{ __('Promo redemptions') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <form method="post" action="{{ route('admin.finance.promo.toggle', $promo) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ $promo->is_active ? __('Promo deactivate') : __('Promo activate') }}">
                                                <i class="bi bi-{{ $promo->is_active ? 'pause' : 'play' }}-fill"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">{{ __('Promo codes empty') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($promoCodes->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $promoCodes->appends(array_merge(['tab' => 'promo'], $promoFilters))->links('pagination::bootstrap-4') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
