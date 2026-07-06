@component('component.card', [
    'title' => __('Backlink administration'),
    'titleHtml' => e(__('Backlink administration')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-backlink'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-backlink.css') }}?v={{ @filemtime(public_path('css/cabinet-backlink.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-module-registry.css') }}?v={{ @filemtime(public_path('css/cabinet-module-registry.css')) ?: time() }}">
    @endslot

    <div class="cabinet-backlink-page cabinet-mod-config-page">
        @include('backlink.partials.module-nav', ['active' => 'config', 'admin' => true])

        <div class="row g-3 align-items-start">
            <div class="col-xl-8">
                <div class="card card-outline card-primary shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title mb-0">{{ __('Global notification settings') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-secondary small">{{ __('Backlink admin notify lead') }}</p>

                        <form action="{{ route('backlink.edit.config') }}" method="post" class="row g-3">
                            @csrf

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="default_notify_telegram" id="default_notify_telegram" value="1"
                                           @if($config->default_notify_telegram) checked @endif>
                                    <label class="form-check-label" for="default_notify_telegram">{{ __('Backlink default notify telegram') }}</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="default_notify_email" id="default_notify_email" value="1"
                                           @if($config->default_notify_email) checked @endif>
                                    <label class="form-check-label" for="default_notify_email">{{ __('Backlink default notify email') }}</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="email_notifications_enabled" id="email_notifications_enabled" value="1"
                                           @if($config->email_notifications_enabled) checked @endif>
                                    <label class="form-check-label" for="email_notifications_enabled">{{ __('Email notifications (module)') }}</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="telegram_notifications_enabled" id="telegram_notifications_enabled" value="1"
                                           @if($config->telegram_notifications_enabled) checked @endif>
                                    <label class="form-check-label" for="telegram_notifications_enabled">{{ __('Telegram notifications (module)') }}</label>
                                </div>
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
                    <div class="card-header"><h3 class="card-title mb-0">{{ __('How checks run') }}</h3></div>
                    <div class="card-body small text-secondary">
                        <p class="mb-2">{{ __('Backlink cron explain') }}</p>
                        <ul class="mb-0 ps-3">
                            <li><code>GET /api/backlink/scan-broken-links</code></li>
                            <li><code>GET /api/backlink/scan-links</code></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-primary"><i class="bi bi-folder2" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Backlink projects count') }}</span>
                        <span class="info-box-number">{{ number_format($stats['projects_total'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('With notifications on') }}: {{ number_format($stats['projects_notify_on'] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
                <div class="info-box shadow-sm mb-3">
                    <span class="info-box-icon text-bg-danger"><i class="bi bi-link-45deg" aria-hidden="true"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Backlink links broken') }}</span>
                        <span class="info-box-number">{{ number_format($stats['links_broken'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="info-box-text">{{ __('Backlink links total') }}: {{ number_format($stats['links_total'] ?? 0, 0, ',', ' ') }}</span>
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

        @include('backlink.partials.config-registry', ['registry' => $registry])
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/search.js') }}"></script>
        <script>
            $(document).ready(function () {
                var $table = $('#cabinet-bl-registry-table');
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
