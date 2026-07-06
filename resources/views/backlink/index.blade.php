@component('component.card', ['title' => __('Link tracking')])
    @slot('css')
        @include('backlink.partials.styles')
    @endslot

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $projects = collect($backlinks);
        $linksTotal = (int) $projects->sum('total_link');
        $linksBroken = (int) $projects->sum('total_broken_link');
    @endphp

    <div class="cabinet-backlink-page">
        @include('backlink.partials.module-nav', ['active' => 'projects', 'admin' => $admin ?? false])

        <div class="d-flex flex-column gap-2">
            @include('backlink.partials.free-tariff-email-notice')
            @include('partials.cabinet-telegram-notify-notice', ['extraClass' => 'cabinet-bl-telegram-notice'])
        </div>

        <div class="cabinet-bl-lead px-4 py-3">
            <div class="d-flex gap-3 align-items-start">
                <span class="cabinet-bl-lead__icon" aria-hidden="true">
                    <i class="bi bi-link-45deg"></i>
                </span>
                <div>
                    <p class="mb-1 fw-semibold text-body">{{ __('Backlink index lead title') }}</p>
                    <p class="mb-0 small text-secondary">{{ __('Backlink index lead hint') }}</p>
                </div>
            </div>
        </div>

        @if($projects->isNotEmpty())
            <div class="cabinet-bl-kpi-row">
                <div class="cabinet-bl-kpi">
                    <div class="cabinet-bl-kpi__value">{{ number_format($projects->count(), 0, ',', ' ') }}</div>
                    <div class="cabinet-bl-kpi__label">{{ __('Backlink projects count') }}</div>
                </div>
                <div class="cabinet-bl-kpi">
                    <div class="cabinet-bl-kpi__value">{{ number_format($linksTotal, 0, ',', ' ') }}</div>
                    <div class="cabinet-bl-kpi__label">{{ __('Backlink links total') }}</div>
                </div>
                <div class="cabinet-bl-kpi{{ $linksBroken > 0 ? ' cabinet-bl-kpi--danger' : '' }}">
                    <div class="cabinet-bl-kpi__value">{{ number_format($linksBroken, 0, ',', ' ') }}</div>
                    <div class="cabinet-bl-kpi__label">{{ __('Backlink links broken') }}</div>
                </div>
            </div>
        @endif

        <div class="cabinet-bl-toolbar">
            <p class="mb-0 small text-secondary">{{ __('My Projects') }}</p>
            <div class="cabinet-bl-toolbar__actions d-flex flex-wrap gap-2">
                <a href="{{ route('add.backlink.view') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add link tracking') }}
                </a>
            </div>
        </div>

        @if($projects->isEmpty())
            <div class="cabinet-bl-empty">
                <i class="bi bi-inbox display-6 text-secondary opacity-50 d-block mb-2" aria-hidden="true"></i>
                <p class="mb-0">{{ __('Backlink empty projects') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle cabinet-bl-index-table mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">{{ __('Project name') }}</th>
                        <th scope="col" class="text-center">{{ __('Broken links/Total links') }}</th>
                        <th scope="col" class="text-center cabinet-bl-th-notify">{{ __('Notifications') }}</th>
                        <th scope="col" class="text-end" style="width: 5rem;">{{ __('Backlink col actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($projects as $backlink)
                        <tr id="{{ $backlink->id }}">
                            <td>
                                <a href="{{ route('show.backlink', $backlink->id) }}"
                                   class="cabinet-bl-project-link click_tracking"
                                   data-click="Show project">
                                    {{ $backlink->project_name }}
                                </a>
                            </td>
                            <td class="text-center">
                                @if($backlink->total_broken_link != 0)
                                    <span class="badge text-bg-danger">
                                        {{ $backlink->total_broken_link }}/{{ $backlink->total_link }}
                                    </span>
                                @else
                                    <span class="badge text-bg-success">
                                        {{ $backlink->total_broken_link }}/{{ $backlink->total_link }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-center cabinet-bl-td-notify">
                                <div class="cabinet-bl-notify-group">
                                <div class="form-check form-switch form-check-sm cabinet-bl-notify-row">
                                    <input type="checkbox"
                                           name="notify_telegram"
                                           class="form-check-input"
                                           role="switch"
                                           @if($backlink->notify_telegram) checked @endif
                                           id="bl-tg-{{ $backlink->id }}">
                                    <label class="form-check-label small" for="bl-tg-{{ $backlink->id }}">{{ __('Telegram') }}</label>
                                </div>
                                <div class="form-check form-switch form-check-sm cabinet-bl-notify-row">
                                    <input type="checkbox"
                                           name="notify_email"
                                           class="form-check-input"
                                           role="switch"
                                           @if($backlink->notify_email) checked @endif
                                           @if(!($backlinkEmailAvailable ?? true)) disabled @endif
                                           id="bl-email-{{ $backlink->id }}">
                                    <label class="form-check-label small @if(!($backlinkEmailAvailable ?? true)) text-secondary @endif"
                                           for="bl-email-{{ $backlink->id }}">{{ __('Notify toggle email') }}</label>
                                </div>
                                </div>
                            </td>
                            <td class="text-end">
                                <form action="{{ route('delete.backlink', $backlink->id) }}"
                                      method="post"
                                      class="d-inline"
                                      onsubmit='return confirm(@json(__('Backlink confirm delete project', ['name' => $backlink->project_name])))'>
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger click_tracking"
                                            type="submit"
                                            data-click="Remove Project"
                                            title="{{ __('Backlink delete project') }}"
                                            aria-label="{{ __('Backlink delete project') }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @slot('js')
        @include('backlink.partials.toasts')
        <script>
            (function () {
                var $page = $('.cabinet-backlink-page');
                var emailAvailable = @json($backlinkEmailAvailable ?? true);
                var freeEmailNotice = @json(__('Backlink free tariff email notice title'));

                function showToast(type, msg) {
                    var selector = type === 'success' ? '.success-message' : '.error-message';
                    var $wrap = $page.find(selector);
                    if (msg) {
                        $wrap.find('.toast-message').first().text(msg);
                    }
                    $wrap.show();
                    setTimeout(function () {
                        $wrap.hide(300);
                    }, 4000);
                }

                $page.on('change', '.cabinet-bl-notify-group input[type="checkbox"]', function () {
                    var $input = $(this);
                    if (!emailAvailable && $input.attr('name') === 'notify_email') {
                        $input.prop('checked', false);
                        showToast('error', freeEmailNotice);
                        return;
                    }
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        url: "{{ route('edit.backlink') }}",
                        data: {
                            id: $input.closest('tr').attr('id'),
                            name: $input.attr('name'),
                            option: $input.is(':checked') ? 1 : 0,
                            _token: $('meta[name="csrf-token"]').attr('content'),
                        },
                        success: function () {
                            showToast('success');
                        },
                        error: function (xhr) {
                            $input.prop('checked', !$input.is(':checked'));
                            var msg = xhr.responseJSON && xhr.responseJSON.message
                                ? xhr.responseJSON.message
                                : null;
                            showToast('error', msg);
                        },
                    });
                });

                $page.on('click', 'label[for^="bl-email-"]', function (e) {
                    if (!emailAvailable) {
                        e.preventDefault();
                        showToast('error', freeEmailNotice);
                    }
                });
            })();
        </script>
    @endslot
@endcomponent
