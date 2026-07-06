@php
    $summary = $registry['summary'] ?? [];
    $rows = $registry['rows'] ?? [];
@endphp

<div class="cabinet-mod-registry mt-4">
    <div class="mb-3">
        <h3 class="h5 mb-1">{{ __('Domain information registry title') }}</h3>
        <p class="text-secondary small mb-0">{{ __('Domain information registry lead') }}</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mod-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mod-registry-kpi__icon text-bg-primary"><i class="bi bi-globe2" aria-hidden="true"></i></div>
                    <div class="cabinet-mod-registry-kpi__value">{{ number_format($summary['domains_total'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mod-registry-kpi__label">{{ __('Monitored domains') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mod-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mod-registry-kpi__icon text-bg-secondary"><i class="bi bi-people" aria-hidden="true"></i></div>
                    <div class="cabinet-mod-registry-kpi__value">{{ number_format($summary['users_with_domains'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mod-registry-kpi__label">{{ __('Site monitoring active users') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mod-registry-kpi card h-100 border-0 shadow-sm @if(($summary['domains_broken'] ?? 0) > 0) cabinet-mod-registry-kpi--alert @endif">
                <div class="card-body">
                    <div class="cabinet-mod-registry-kpi__icon text-bg-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
                    <div class="cabinet-mod-registry-kpi__value">{{ number_format($summary['domains_broken'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mod-registry-kpi__label">{{ __('Domain information broken now') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mod-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mod-registry-kpi__label mb-2">{{ __('Domain information registry notify summary') }}</div>
                    <div class="small text-secondary">
                        {{ __('Check DNS') }}: <strong>{{ number_format($summary['domains_notify_dns'] ?? 0, 0, ',', ' ') }}</strong><br>
                        {{ __('Check registration date') }}: <strong>{{ number_format($summary['domains_notify_registration'] ?? 0, 0, ',', ' ') }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small mb-3 py-2 cabinet-mod-registry-notify-legend" role="note">
        <span class="fw-semibold me-1">{{ __('Site monitoring registry notify legend title') }}:</span>
        <span class="badge rounded-pill cabinet-mod-registry-notify-badge cabinet-mod-registry-notify-badge--telegram me-1"><i class="bi bi-telegram me-1" aria-hidden="true"></i>TG</span>{{ __('Site monitoring registry notify legend telegram') }}
        <span class="mx-2 text-secondary">·</span>
        <span class="badge rounded-pill cabinet-mod-registry-notify-badge cabinet-mod-registry-notify-badge--email me-1"><i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ __('Notify toggle email') }}</span>{{ __('Site monitoring registry notify legend email') }}
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if(count($rows) === 0)
                <div class="alert alert-secondary m-3 mb-0">{{ __('Domain information registry empty') }}</div>
            @else
                <div class="cabinet-mod-datatable p-3 pt-2">
                    <table id="cabinet-di-registry-table" class="table table-sm table-bordered table-striped align-middle cabinet-mod-registry-table w-100 mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th class="text-nowrap">{{ __('Last visit') }}</th>
                            <th>{{ __('Tariff') }}</th>
                            <th>{{ __('Domain') }}</th>
                            <th class="text-center">{{ __('Check DNS') }}</th>
                            <th class="text-center">{{ __('Check registration date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-nowrap">{{ __('Last check') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr class="@if($row['broken']) table-danger @endif">
                                <td data-order="{{ $row['email'] }}">
                                    <div class="cabinet-mod-registry-user">
                                        <div class="fw-semibold text-break">{{ $row['email'] }}</div>
                                        @if($row['name'])<div class="text-secondary small">{{ $row['name'] }}</div>@endif
                                        <div class="text-secondary small">ID {{ $row['user_id'] }}</div>
                                    </div>
                                </td>
                                <td data-order="{{ $row['last_online_sort'] }}" class="text-nowrap small">
                                    @if($row['last_online_at'])
                                        <div>{{ $row['last_online_at'] }}</div>
                                        <div class="text-secondary">{{ $row['last_online_human'] }}</div>
                                    @else — @endif
                                </td>
                                <td data-order="{{ $row['tariff_sort'] }}"><span class="badge text-bg-secondary">{{ $row['tariff_label'] }}</span></td>
                                <td data-order="{{ $row['domain'] }}"><span class="fw-medium">{{ $row['domain'] }}</span></td>
                                <td data-order="{{ $row['dns_delivery_sort'] }}" class="text-center">
                                    @include('partials.cabinet-registry-notify-delivery', ['delivery' => $row['dns_delivery'], 'hint' => $row['dns_delivery_hint']])
                                </td>
                                <td data-order="{{ $row['registration_delivery_sort'] }}" class="text-center">
                                    @include('partials.cabinet-registry-notify-delivery', ['delivery' => $row['registration_delivery'], 'hint' => $row['registration_delivery_hint']])
                                </td>
                                <td data-order="{{ $row['broken'] ? 0 : 1 }}">
                                    @if($row['broken'])
                                        <span class="badge text-bg-danger">{{ __('broken') }}</span>
                                    @else
                                        <span class="badge text-bg-success">{{ __('Everything all right') }}</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['last_check_sort'] }}" class="text-nowrap small text-secondary">{{ $row['last_check'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
