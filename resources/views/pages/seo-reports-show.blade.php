@component('component.card', [
    'title' => $project->domain,
    'documentTitle' => $project->domain . ' · ' . __('SEO Reports'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'project',
            'srContextProject' => $project,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ $project->domain }}</h1>
                <p class="cabinet-sr-hero__lead">
                    {{ $project->title ?: __('SEO report project') }}
                </p>
            </div>
            <div class="cabinet-sr-actions">
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('pages.seo-reports.compare', ['id' => $project->id]) }}">
                    {{ __('Compare') }}
                </a>
                @if(!empty($isOwner))
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">
                        {{ __('Settings') }}
                    </a>
                    <form method="post" action="{{ route('pages.seo-reports.demo') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="project_id" value="{{ $project->id }}">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Create demo report') }}</button>
                    </form>
                @endif
                @if(!empty($canEdit))
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetSrGenerateModal">
                        {{ __('Generate report') }}
                    </button>
                @elseif(!empty($shareRole))
                    <span class="cabinet-sr-badge cabinet-sr-badge--manual">{{ __('Read only') }}</span>
                @endif
            </div>
        </div>

        @if(!empty($generationWarnings))
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <div class="fw-semibold mb-1">{{ __('Before generate') }}</div>
                <ul class="mb-0 ps-3">
                    @foreach($generationWarnings as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="cabinet-sr-dq mb-3">
            <div class="cabinet-sr-dq__head">
                <span class="fw-semibold">{{ __('Connections health') }}</span>
            </div>
            <ul class="cabinet-sr-dq__list">
                @foreach($sections as $section)
                    @if(in_array($section['source'], ['manual', 'computed'], true))
                        @continue
                    @endif
                    <li>
                        <strong>{{ $section['title'] }}</strong>
                        ·
                        @if($section['source_status'] === 'ok')
                            <span class="text-success">{{ __('Connected') }}</span>
                        @elseif($section['source_status'] === 'manual')
                            {{ __('Manual') }}
                        @else
                            <span class="text-warning">{{ __('Not connected') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if(!empty($isOwner))
            <div class="cabinet-sr-dq mb-3">
                <div class="cabinet-sr-dq__head">
                    <span class="fw-semibold">{{ __('Project sharing') }}</span>
                </div>
                <form method="post" action="{{ route('pages.seo-reports.share', ['id' => $project->id]) }}" class="mb-2">
                    @csrf
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <div>
                            <label class="form-label small mb-1">{{ __('Email') }}</label>
                            <input type="email" name="email" class="form-control form-control-sm" required placeholder="user@example.com">
                        </div>
                        <div>
                            <label class="form-label small mb-1">{{ __('Access role') }}</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="read">{{ __('Read only') }}</option>
                                <option value="edit">{{ __('Editor') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Share') }}</button>
                    </div>
                </form>
                @if(($sharedUsers ?? collect())->isNotEmpty())
                    <ul class="cabinet-sr-dq__list mb-0">
                        @foreach($sharedUsers as $su)
                            <li class="d-flex justify-content-between gap-2">
                                <span>{{ $su->email }} · {{ ($su->pivot->role ?? 'read') === 'edit' ? __('Editor') : __('Read only') }}</span>
                                <form method="post" action="{{ route('pages.seo-reports.unshare', ['id' => $project->id]) }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $su->id }}">
                                    <button type="submit" class="btn btn-link btn-sm p-0">{{ __('Revoke') }}</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <h2 class="h6 mb-2">{{ __('Reports') }}</h2>
                @if($reports->isEmpty())
                    <div class="cabinet-sr-empty py-4">
                        <p class="mb-2">{{ __('No reports yet') }}</p>
                        @if(!empty($canEdit))
                            <button type="button"
                                    class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#cabinetSrGenerateModal">
                                {{ __('Generate report') }}
                            </button>
                        @endif
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="cabinet-sr-table">
                            <thead>
                            <tr>
                                <th>{{ __('Period') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($reports as $report)
                                <tr>
                                    <td>
                                        {{ optional($report->period_from)->format('d.m.Y') }}
                                        —
                                        {{ optional($report->period_to)->format('d.m.Y') }}
                                        @if(!empty($report->archived_from_report_id))
                                            <span class="cabinet-sr-badge cabinet-sr-badge--warn">{{ __('Previous version') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="cabinet-sr-badge cabinet-sr-badge--manual">{{ $report->statusLabel() }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('pages.seo-reports.report', ['id' => $project->id, 'reportId' => $report->id]) }}">
                                            {{ __('Open') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                <h2 class="h6 mb-2">{{ __('Report sections') }}</h2>
                <p class="small text-secondary mb-2">{{ __('SEO report sections manager hint') }}</p>
                <div class="cabinet-sr-section-list">
                    @foreach($sections as $section)
                        @php
                            $dead = in_array($section['source_status'], ['not_connected', 'error', 'empty'], true);
                            $cls = !$section['enabled'] ? 'cabinet-sr-section--off' : ($dead ? 'cabinet-sr-section--dead' : '');
                            $badgeCls = 'cabinet-sr-badge--off';
                            $badgeText = __('Off');
                            if ($section['enabled'] && $section['source_status'] === 'ok') {
                                $badgeCls = 'cabinet-sr-badge--ok';
                                $badgeText = __('Connected');
                            } elseif ($section['enabled'] && $section['source_status'] === 'manual') {
                                $badgeCls = 'cabinet-sr-badge--manual';
                                $badgeText = __('Manual');
                            } elseif ($section['enabled'] && $dead) {
                                $badgeCls = 'cabinet-sr-badge--warn';
                                $badgeText = __('Not connected');
                            }
                        @endphp
                        <div class="cabinet-sr-section {{ $cls }}">
                            <p class="cabinet-sr-section__title">{{ $section['title'] }}</p>
                            <span class="cabinet-sr-badge {{ $badgeCls }}">{{ $badgeText }}</span>
                            @if($section['enabled'] && !$section['client_visible'])
                                <span class="small text-secondary">{{ __('Hidden for client') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if(!empty($isOwner))
            <form method="post" action="{{ route('pages.seo-reports.archive', ['id' => $project->id]) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm"
                        onclick="return confirm(@json(__('Archive this project?')))">
                    {{ __('Archive') }}
                </button>
            </form>
        @endif
    </div>

    @if(!empty($canEdit))
        <div class="modal fade" id="cabinetSrGenerateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="{{ route('pages.seo-reports.reports.store', ['id' => $project->id]) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Generate report') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Period preset') }}</label>
                            <select class="form-select" name="period_preset" data-sr-period-preset>
                                <option value="prev_month">{{ __('Previous calendar month') }}</option>
                                <option value="last_30">{{ __('Last 30 days') }}</option>
                                <option value="custom">{{ __('Custom dates') }}</option>
                            </select>
                        </div>
                        <div class="row g-2" data-sr-custom-dates style="display:none">
                            <div class="col-6">
                                <label class="form-label">{{ __('Date from') }}</label>
                                <input type="date" class="form-control" name="period_from">
                            </div>
                            <div class="col-6">
                                <label class="form-label">{{ __('Date to') }}</label>
                                <input type="date" class="form-control" name="period_to">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">{{ __('PIN (optional)') }}</label>
                            <input type="text" class="form-control" name="public_pin" maxlength="8" inputmode="numeric" placeholder="1234">
                        </div>
                        <p class="small text-secondary mb-0 mt-2">{{ __('SEO report generate hint') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Generate report') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @slot('js')
        <script>
            (function () {
                var preset = document.querySelector('[data-sr-period-preset]');
                var custom = document.querySelector('[data-sr-custom-dates]');
                if (!preset || !custom) return;
                preset.addEventListener('change', function () {
                    custom.style.display = preset.value === 'custom' ? '' : 'none';
                });
            })();
        </script>
    @endslot
@endcomponent
