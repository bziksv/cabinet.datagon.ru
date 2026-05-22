@php
    $avatarUrl = $user->image ?: asset('img/user-icon.svg');
    $langLabel = $lang[$user->lang] ?? $user->lang;
    $displayName = trim($user->full_name) ?: $user->email;
@endphp
<div class="card shadow-sm cabinet-user-edit-aside">
    <div class="card-body text-center">
        <div class="position-relative d-inline-block mb-3">
            <img src="{{ $avatarUrl }}"
                 alt="{{ $displayName }}"
                 class="rounded-circle shadow cabinet-user-edit-avatar"
                 width="96"
                 height="96">
            @if($telegramConnected)
                <span class="position-absolute bottom-0 end-0 badge text-bg-info rounded-pill"
                      title="{{ __('Telegram connected') }}">
                    <i class="bi bi-telegram" aria-hidden="true"></i>
                </span>
            @endif
        </div>
        <h3 class="h5 mb-1 text-break">{{ $displayName }}</h3>
        <p class="text-secondary small mb-2 text-break">{{ $user->email }}</p>
        <p class="text-secondary small mb-2">ID {{ $user->id }}</p>

        @if($user->email_verified_at)
            <span class="badge text-bg-success mb-2">{{ __('VERIFIED') }}</span>
        @else
            <span class="badge text-bg-warning mb-2">{{ __('No verified user') }}</span>
        @endif

        <div class="mb-2">
            @forelse($user->roles as $roleModel)
                <span class="badge text-bg-secondary me-1 mb-1">{{ __($roleModel->name) }}</span>
            @empty
                <span class="badge text-bg-light text-secondary border">{{ __('No roles assigned') }}</span>
            @endforelse
        </div>

        <ul class="list-group list-group-flush text-start small mt-2">
            @if($tariffName)
                <li class="list-group-item d-flex justify-content-between align-items-start px-0 gap-2">
                    <span class="text-secondary">{{ __('Tariff') }}</span>
                    <span class="fw-semibold text-end">{{ $tariffName }}</span>
                </li>
            @endif
            @if($activePay && $activePay->active_to)
                <li class="list-group-item d-flex justify-content-between align-items-start px-0 gap-2">
                    <span class="text-secondary">{{ __('Active until') }}</span>
                    <span class="fw-semibold text-end text-nowrap">
                        {{ $activePay->active_to->format('d.m.Y') }}
                        <br>
                        <span class="text-secondary fw-normal">{{ $activePay->active_to->diffForHumans() }}</span>
                    </span>
                </li>
            @endif
            <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-secondary">{{ __('Lang') }}</span>
                <span class="fw-semibold">{{ $langLabel }}</span>
            </li>
            @if($user->last_online_at)
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-secondary">{{ __('Was online') }}</span>
                    <span class="fw-semibold text-end">{{ $user->last_online_at->diffForHumans() }}</span>
                </li>
            @else
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-secondary">{{ __('Was online') }}</span>
                    <span class="text-secondary">{{ __('Never') }}</span>
                </li>
            @endif
            @if($user->created_at)
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-secondary">{{ __('Created') }}</span>
                    <span class="fw-semibold">{{ $user->created_at->format('d.m.Y') }}</span>
                </li>
            @endif
            @if($canManageStatistic ?? false)
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-secondary">{{ __('Track statistic') }}</span>
                    <span class="fw-semibold">
                        @if($user->statistic)
                            <span class="badge text-bg-success">{{ __('Yes') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('No') }}</span>
                        @endif
                    </span>
                </li>
            @endif
        </ul>

        <div class="d-grid gap-2 mt-3">
            <a href="{{ route('visit.statistics', $user->id) }}" class="btn btn-outline-info btn-sm">
                <i class="bi bi-pie-chart me-1"></i>{{ __('User statistic') }}
            </a>
            <a href="{{ route('users.login', $user->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('Login') }}
            </a>
            <a href="{{ route('users.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people me-1"></i>{{ __('Users') }}
            </a>
        </div>
    </div>
</div>
