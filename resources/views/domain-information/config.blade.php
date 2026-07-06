@component('component.card', [
    'title' => __('Domain information administration'),
    'titleHtml' => e(__('Domain information administration')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-domain-information'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-domain-information.css') }}?v={{ @filemtime(public_path('css/cabinet-domain-information.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-module-registry.css') }}?v={{ @filemtime(public_path('css/cabinet-module-registry.css')) ?: time() }}">
    @endslot

    <div class="cabinet-di-page cabinet-mod-config-page">
        @include('domain-information.partials.module-nav', ['active' => 'config', 'admin' => true])

        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title mb-0">{{ __('Global notification settings') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ __('Domain information admin notify lead') }}</p>

                        <form action="{{ route('domain.information.edit.config') }}" method="post" class="row g-3">
                            @csrf

                            <div class="col-md-6">
                                <label class="form-label" for="expiration_alert_days">
                                    {{ __('Domain information expiration alert days') }}
                                </label>
                                <div class="input-group">
                                    <input type="number"
                                           name="expiration_alert_days"
                                           id="expiration_alert_days"
                                           class="form-control"
                                           min="1"
                                           max="365"
                                           required
                                           value="{{ old('expiration_alert_days', $config->expiration_alert_days) }}">
                                    <span class="input-group-text">{{ __('days') }}</span>
                                </div>
                                <p class="form-text mb-0">{{ __('Domain information expiration alert hint') }}</p>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="default_check_dns" id="default_check_dns" value="1"
                                           @if($config->default_check_dns) checked @endif>
                                    <label class="form-check-label" for="default_check_dns">{{ __('Domain information default check dns') }}</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="default_check_registration_date" id="default_check_registration_date" value="1"
                                           @if($config->default_check_registration_date) checked @endif>
                                    <label class="form-check-label" for="default_check_registration_date">{{ __('Domain information default check registration') }}</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="email_notifications_enabled" id="email_notifications_enabled" value="1"
                                           @if($config->email_notifications_enabled) checked @endif>
                                    <label class="form-check-label" for="email_notifications_enabled">{{ __('Email notifications (module)') }}</label>
                                </div>
                                <p class="form-text mb-0">{{ __('Site monitoring email channel hint') }}</p>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="telegram_notifications_enabled" id="telegram_notifications_enabled" value="1"
                                           @if($config->telegram_notifications_enabled) checked @endif>
                                    <label class="form-check-label" for="telegram_notifications_enabled">{{ __('Telegram notifications (module)') }}</label>
                                </div>
                                <p class="form-text mb-0">{{ __('Site monitoring telegram channel hint') }}</p>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('Update') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-header">
                        <h3 class="card-title mb-0">{{ __('How checks run') }}</h3>
                    </div>
                    <div class="card-body small text-secondary">
                        <p class="mb-2">{{ __('Domain information cron explain') }}</p>
                        <ul class="mb-0 ps-3">
                            <li><code>GET /api/domain-information/check-domain-crone/</code></li>
                            <li>{{ __('Domain information admin free policy') }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-primary"><i class="bi bi-globe2" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Monitored domains') }}</span>
                        <span class="info-box-number">{{ number_format($stats['domains_total'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Domain information notify dns on') }}: {{ number_format($stats['domains_notify_dns'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-warning"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Domain information notify registration on') }}</span>
                        <span class="info-box-number">{{ number_format($stats['domains_notify_registration'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Site monitoring active users') }}: {{ number_format($stats['users_with_domains'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm">
                    <span class="info-box-icon text-bg-info"><i class="bi bi-telegram" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Users with Telegram') }}</span>
                        <span class="info-box-number">{{ number_format($stats['users_telegram'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('domain-information.partials.config-registry', ['registry' => $registry])
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/search.js') }}"></script>
        <script>
            $(document).ready(function () {
                var $table = $('#cabinet-di-registry-table');
                if (!$table.length) return;
                var registryTable = $table.DataTable({
                    dom: '<"row align-items-center g-2 cabinet-mod-dt-controls"<"col-sm-auto"l><"col-sm-auto ms-auto"f>>rt<"row align-items-center g-2 cabinet-mod-dt-footer"<"col-sm-auto"i><"col-sm-auto ms-auto"p>>',
                    autoWidth: false,
                    pageLength: 25,
                    order: [[0, 'asc'], [3, 'asc']],
                    language: { paginate: { first: '«', last: '»', next: '»', previous: '«' } },
                    oLanguage: {
                        sSearch: @json(__('Search') . ':'),
                        sLengthMenu: @json(__('show') . ' _MENU_ ' . __('records')),
                        sEmptyTable: @json(__('No records')),
                        sInfo: @json(__('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')),
                    },
                });
                if (typeof search === 'function') search(registryTable);
            });
        </script>
    @endslot
@endcomponent
