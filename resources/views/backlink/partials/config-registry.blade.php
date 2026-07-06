@php
    $summary = $registry['summary'] ?? [];
    $rows = $registry['rows'] ?? [];
@endphp

<div class="cabinet-mod-registry mt-4">
    <div class="mb-3">
        <h3 class="h5 mb-1">{{ __('Backlink registry title') }}</h3>
        <p class="text-secondary small mb-0">{{ __('Backlink registry lead') }}</p>
    </div>

    <div class="alert alert-light border small mb-3 py-2 cabinet-mod-registry-notify-legend" role="note">
        <span class="fw-semibold me-1">{{ __('Site monitoring registry notify legend title') }}:</span>
        <span class="badge rounded-pill cabinet-mod-registry-notify-badge cabinet-mod-registry-notify-badge--telegram me-1"><i class="bi bi-telegram me-1" aria-hidden="true"></i>TG</span>
        <span class="badge rounded-pill cabinet-mod-registry-notify-badge cabinet-mod-registry-notify-badge--email me-1"><i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ __('Notify toggle email') }}</span>
        {{ __('Site monitoring registry notify legend free') }}
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if(count($rows) === 0)
                <div class="alert alert-secondary m-3 mb-0">{{ __('Backlink registry empty') }}</div>
            @else
                <div class="cabinet-mod-datatable p-3 pt-2">
                    <table id="cabinet-bl-registry-table" class="table table-sm table-bordered table-striped align-middle cabinet-mod-registry-table w-100 mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th class="text-nowrap">{{ __('Last visit') }}</th>
                            <th>{{ __('Tariff') }}</th>
                            <th>{{ __('Project name') }}</th>
                            <th class="text-center">{{ __('Broken links/Total links') }}</th>
                            <th class="text-center">{{ __('Site monitoring registry notify delivery') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr class="@if($row['total_broken_link'] > 0) table-warning @endif">
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
                                <td data-order="{{ $row['project_name'] }}"><span class="fw-medium">{{ $row['project_name'] }}</span></td>
                                <td data-order="{{ $row['total_broken_link'] }}" class="text-center">
                                    @if($row['total_broken_link'] > 0)
                                        <span class="badge text-bg-danger">{{ $row['total_broken_link'] }}/{{ $row['total_link'] }}</span>
                                    @else
                                        <span class="badge text-bg-success">{{ $row['total_broken_link'] }}/{{ $row['total_link'] }}</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['notify_delivery_sort'] }}" class="text-center">
                                    @include('partials.cabinet-registry-notify-delivery', [
                                        'mode' => $row['notify_delivery_mode'],
                                        'hint' => $row['notify_delivery_hint'],
                                        'notifyTelegram' => $row['notify_telegram'],
                                        'notifyEmail' => $row['notify_email'],
                                    ])
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
