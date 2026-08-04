@component('component.card', [
    'title' => __('Reports'),
    'documentTitle' => __('Reports'),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'projects',
            'srProjectsCount' => $projects->count(),
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ __('Projects') }}</h1>
                <p class="cabinet-sr-hero__lead">{{ __('SEO reports lead') }}</p>
            </div>
            @if(count($availableDomains) > 0)
                <div class="cabinet-sr-actions">
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetSrCreateModal">
                        {{ __('Create SEO report project') }}
                    </button>
                </div>
            @endif
        </div>

        @if(!empty($missingReports))
            <div class="alert alert-warning py-2 px-3 small mb-3">
                <div class="fw-semibold mb-1">{{ __('Missing monthly reports') }} · {{ $missingMonthLabel ?? '' }}</div>
                <ul class="mb-0 ps-3">
                    @foreach($missingReports as $mp)
                        <li>
                            <a href="{{ route('pages.seo-reports.show', ['id' => $mp->id]) }}">{{ $mp->domain }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($projects->isEmpty())
            <div class="cabinet-sr-empty">
                <i class="bi bi-file-earmark-bar-graph display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1 text-body">{{ __('No SEO report projects yet') }}</p>
                <p class="small mb-3">{{ __('No SEO report projects yet hint') }}</p>
                @if(count($availableDomains) > 0)
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetSrCreateModal">
                        {{ __('Create SEO report project') }}
                    </button>
                @endif
            </div>
        @else
            <div class="cabinet-sr-toolbar cabinet-sr-toolbar--projects">
                <input type="search"
                       class="form-control form-control-sm"
                       placeholder="{{ __('Search projects') }}…"
                       data-sr-project-search
                       autocomplete="off">
                <form method="post" action="{{ route('pages.seo-reports.batch') }}" class="cabinet-sr-batch" id="cabinetSrBatchForm">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Batch generate previous month') }}</button>
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="force" value="1">
                        <span class="small">{{ __('Force regenerate existing') }}</span>
                    </label>
                </form>
            </div>
            <div class="cabinet-sr-project-list" data-sr-project-grid>
                @foreach($projects as $project)
                    @php
                        $isShared = !empty($sharedProjectIds) && in_array($project->id, $sharedProjectIds, true);
                        $isTeam = !empty($teamProjectIds) && in_array($project->id, $teamProjectIds, true);
                        $isOwner = (int) $project->user_id === (int) Auth::id();
                        $projSettings = is_array($project->settings_json) ? $project->settings_json : [];
                        $hasMetrika = (int) ($project->metrika_counter_id ?? 0) > 0;
                        $hasMonitoring = (int) ($project->monitoring_project_id ?? 0) > 0;
                        $hasWm = trim((string) ($projSettings['webmaster_host'] ?? '')) !== '';
                        $reportsCount = (int) $project->reports_count;
                        $title = trim((string) ($project->title ?? ''));
                    @endphp
                    <article class="cabinet-sr-project"
                             data-sr-project-card
                             data-domain="{{ $project->domain }}"
                             data-title="{{ $title }}">
                        <div class="cabinet-sr-project__top">
                            <label class="cabinet-sr-project__select" title="{{ __('Select for batch') }}">
                                <input type="checkbox" form="cabinetSrBatchForm" name="project_ids[]" value="{{ $project->id }}">
                                <span class="visually-hidden">{{ __('Select') }}</span>
                            </label>
                            <div class="cabinet-sr-project__identity">
                                <h2 class="cabinet-sr-project__domain">{{ $project->domain }}</h2>
                                <p class="cabinet-sr-project__title">
                                    {{ $title !== '' ? $title : __('SEO report project') }}
                                    @if($isShared)
                                        <span class="cabinet-sr-project__tag">{{ __('Shared') }}</span>
                                    @elseif($isTeam)
                                        <span class="cabinet-sr-project__tag">{{ __('Team') }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="cabinet-sr-project__reports" title="{{ __('Reports') }}">
                                <strong>{{ $reportsCount }}</strong>
                                <span>{{ __('Reports') }}</span>
                            </div>
                        </div>
                        <ul class="cabinet-sr-project__sources" aria-label="{{ __('Connections health') }}">
                            <li class="is-{{ $hasMetrika ? 'on' : 'off' }}">
                                <i class="bi bi-{{ $hasMetrika ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ __('Metrika') }}
                            </li>
                            <li class="is-{{ $hasMonitoring ? 'on' : 'off' }}">
                                <i class="bi bi-{{ $hasMonitoring ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ __('Monitoring') }}
                            </li>
                            <li class="is-{{ $hasWm ? 'on' : 'off' }}">
                                <i class="bi bi-{{ $hasWm ? 'check-circle-fill' : 'circle' }}" aria-hidden="true"></i>
                                {{ __('Webmaster') }}
                            </li>
                        </ul>
                        <div class="cabinet-sr-project__actions">
                            <a class="btn btn-primary btn-sm"
                               href="{{ route('pages.seo-reports.show', ['id' => $project->id]) }}">
                                {{ __('Open project') }}
                            </a>
                            @if($isOwner)
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="{{ route('pages.seo-reports.settings', ['id' => $project->id]) }}">
                                    {{ __('Settings') }}
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        @if($archived->isNotEmpty())
            <details class="mt-4">
                <summary class="small text-secondary" style="cursor:pointer">
                    {{ __('Archived') }} ({{ $archived->count() }})
                </summary>
                <ul class="list-unstyled mt-2 mb-0 small">
                    @foreach($archived as $project)
                        <li class="d-flex align-items-center gap-2 py-1">
                            <span>{{ $project->domain }}</span>
                            <form method="post" action="{{ route('pages.seo-reports.restore', ['id' => $project->id]) }}" class="ms-auto">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0">{{ __('Restore') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>

    <div class="modal fade" id="cabinetSrCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="post" action="{{ route('pages.seo-reports.store') }}" data-sr-wizard>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Create SEO report project') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cabinet-sr-wizard-steps mb-3">
                        <span class="is-active" data-sr-step-label="1">1. {{ __('Domain') }}</span>
                        <span data-sr-step-label="2">2. {{ __('Integrations') }}</span>
                        <span data-sr-step-label="3">3. {{ __('Template') }}</span>
                    </div>

                    <div data-sr-step="1">
                        <div class="mb-3">
                            <label class="form-label" for="cabinetSrDomain">{{ __('Domain') }}</label>
                            <select class="form-select" name="domain" id="cabinetSrDomain" required
                                    data-sr-domain
                                    data-placeholder="{{ __('Select domain') }}">
                                <option value=""></option>
                                @foreach($availableDomains as $domain)
                                    <option value="{{ $domain }}"
                                            data-metrika="{{ $domainHints[$domain]['metrika'] ?? '' }}"
                                            data-metrika-label="{{ $domainHints[$domain]['metrika_label'] ?? '' }}"
                                            data-monitoring="{{ $domainHints[$domain]['monitoring'] ?? '' }}"
                                            data-monitoring-label="{{ $domainHints[$domain]['monitoring_label'] ?? '' }}">
                                        {{ $domain }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="cabinetSrTitle">{{ __('Title') }} <span class="text-secondary">({{ __('optional') }})</span></label>
                            <input type="text" class="form-control" name="title" id="cabinetSrTitle" maxlength="190">
                        </div>
                    </div>

                    <div data-sr-step="2" hidden>
                        <p class="small text-secondary">{{ __('SEO report wizard integrations hint') }}</p>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Yandex Metrika') }}</label>
                            <input type="text" class="form-control form-control-sm" data-sr-hint-metrika readonly
                                   placeholder="{{ __('Will auto-bind by domain') }}">
                            <input type="hidden" name="metrika_counter_id" data-sr-metrika-id value="">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">{{ __('Position monitoring') }}</label>
                            <input type="text" class="form-control form-control-sm" data-sr-hint-monitoring readonly
                                   placeholder="{{ __('Will auto-bind by domain') }}">
                            <input type="hidden" name="monitoring_project_id" data-sr-monitoring-id value="">
                        </div>
                    </div>

                    <div data-sr-step="3" hidden>
                        <label class="form-label mb-1">{{ __('Report template') }}</label>
                        <p class="small text-secondary mb-2">{{ __('SEO report wizard attach template hint') }}</p>

                        @if(!empty($reportTemplates) && $reportTemplates->isNotEmpty())
                            <div class="cabinet-sr-template-pick-list mb-3">
                                @foreach($reportTemplates as $idx => $tpl)
                                    <label class="cabinet-sr-toggle-row">
                                        <input type="radio" name="template_id" value="{{ $tpl->id }}"
                                            @if($tpl->is_default || $idx === 0) checked @endif
                                            data-sr-template-pick>
                                        <span>
                                            <strong>{{ $tpl->title }}</strong>
                                            @if($tpl->is_default)
                                                <small>· {{ __('Default') }}</small>
                                            @endif
                                            @if($tpl->agency_name)
                                                <small class="d-block text-secondary">{{ $tpl->agency_name }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="small text-secondary mb-2">
                                <a href="{{ route('pages.seo-reports.templates') }}" target="_blank" rel="noopener">
                                    {{ __('Manage templates') }}
                                </a>
                                — {{ __('SEO report wizard copy template hint') }}
                            </p>
                        @endif

                        <details class="cabinet-sr-fieldset" @if(empty($reportTemplates) || $reportTemplates->isEmpty()) open @endif>
                            <summary class="small fw-semibold mb-2">{{ __('Or create new template from preset') }}</summary>
                            <input type="hidden" name="force_new_template" value="0" data-sr-force-new-template>
                            <div class="cabinet-sr-preset-list">
                                @foreach(\App\SeoReports\SeoReportSectionRegistry::presetCards() as $idx => $preset)
                                    <div class="cabinet-sr-preset-card">
                                        <label class="cabinet-sr-preset-card__pick">
                                            <input type="radio" name="template" value="{{ $preset['key'] }}"
                                                data-sr-preset-pick
                                                @if((empty($reportTemplates) || $reportTemplates->isEmpty()) && $idx === 0) checked @endif>
                                            <div class="cabinet-sr-preset-card__body">
                                                <div class="cabinet-sr-preset-card__head">
                                                    <strong class="cabinet-sr-preset-card__title">{{ $preset['title'] }}</strong>
                                                    <span class="cabinet-sr-preset-card__count">{{ $preset['sections_count'] }} {{ __('sections') }}</span>
                                                </div>
                                                <p class="cabinet-sr-preset-card__lead mb-0">{{ $preset['lead'] }}</p>
                                            </div>
                                        </label>
                                        <a class="cabinet-sr-preset-card__demo-link"
                                           href="{{ route('pages.seo-reports.preset-demo', ['preset' => $preset['key']]) }}"
                                           target="_blank"
                                           rel="noopener">
                                            {{ __('Open full HTML demo report') }} →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-outline-secondary" data-sr-prev hidden>{{ __('Back') }}</button>
                    <button type="button" class="btn btn-primary" data-sr-next>{{ __('Next') }}</button>
                    <button type="submit" class="btn btn-primary" data-sr-finish hidden>{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                var input = document.querySelector('[data-sr-project-search]');
                var grid = document.querySelector('[data-sr-project-grid]');
                if (input && grid) {
                    input.addEventListener('input', function () {
                        var q = (input.value || '').toLowerCase().trim();
                        grid.querySelectorAll('[data-sr-project-card]').forEach(function (card) {
                            var domain = (card.getAttribute('data-domain') || '').toLowerCase();
                            var title = (card.getAttribute('data-title') || '').toLowerCase();
                            var match = !q || domain.indexOf(q) !== -1 || title.indexOf(q) !== -1;
                            card.style.display = match ? '' : 'none';
                        });
                    });
                }

                var form = document.querySelector('[data-sr-wizard]');
                if (!form) return;
                var step = 1;
                var max = 3;
                var forceNew = form.querySelector('[data-sr-force-new-template]');
                function syncTemplateMode() {
                    if (!forceNew) return;
                    var presetChecked = form.querySelector('[data-sr-preset-pick]:checked');
                    var templateChecked = form.querySelector('[data-sr-template-pick]:checked');
                    forceNew.value = presetChecked && !templateChecked ? '1' : (presetChecked && templateChecked ? '0' : '0');
                    // Selecting a preset means create a fresh template for this project.
                    if (presetChecked) {
                        form.querySelectorAll('[data-sr-template-pick]').forEach(function (el) { el.checked = false; });
                        forceNew.value = '1';
                    }
                }
                form.querySelectorAll('[data-sr-preset-pick]').forEach(function (el) {
                    el.addEventListener('change', syncTemplateMode);
                });
                form.querySelectorAll('[data-sr-template-pick]').forEach(function (el) {
                    el.addEventListener('change', function () {
                        form.querySelectorAll('[data-sr-preset-pick]').forEach(function (p) { p.checked = false; });
                        if (forceNew) forceNew.value = '0';
                    });
                });
                var domainSelect = form.querySelector('[data-sr-domain]');
                var hintM = form.querySelector('[data-sr-hint-metrika]');
                var hintMon = form.querySelector('[data-sr-hint-monitoring]');
                var idM = form.querySelector('[data-sr-metrika-id]');
                var idMon = form.querySelector('[data-sr-monitoring-id]');
                var btnPrev = form.querySelector('[data-sr-prev]');
                var btnNext = form.querySelector('[data-sr-next]');
                var btnFinish = form.querySelector('[data-sr-finish]');
                var modal = document.getElementById('cabinetSrCreateModal');

                function syncHints() {
                    var opt = domainSelect.options[domainSelect.selectedIndex];
                    var m = opt ? (opt.getAttribute('data-metrika') || '') : '';
                    var mon = opt ? (opt.getAttribute('data-monitoring') || '') : '';
                    var mLabel = opt ? (opt.getAttribute('data-metrika-label') || '') : '';
                    var monLabel = opt ? (opt.getAttribute('data-monitoring-label') || '') : '';
                    idM.value = m;
                    idMon.value = mon;
                    hintM.value = m ? (mLabel || m) : '';
                    hintMon.value = mon ? (monLabel || mon) : '';
                    hintM.placeholder = m ? '' : @json(__('Will auto-bind by domain'));
                    hintMon.placeholder = mon ? '' : @json(__('Will auto-bind by domain'));
                }

                function render() {
                    form.querySelectorAll('[data-sr-step]').forEach(function (el) {
                        el.hidden = String(el.getAttribute('data-sr-step')) !== String(step);
                    });
                    form.querySelectorAll('[data-sr-step-label]').forEach(function (el) {
                        el.classList.toggle('is-active', String(el.getAttribute('data-sr-step-label')) === String(step));
                    });
                    btnPrev.hidden = step <= 1;
                    btnNext.hidden = step >= max;
                    btnFinish.hidden = step < max;
                }

                function initDomainSelect2() {
                    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
                        return;
                    }
                    if (!domainSelect) return;
                    var $ = window.jQuery;
                    var $select = $(domainSelect);
                    var $modal = modal ? $(modal) : null;
                    var placeholder = $select.attr('data-placeholder') || '';

                    function mount() {
                        if ($select.hasClass('select2-hidden-accessible')) {
                            $select.select2('destroy');
                        }
                        $select.select2({
                            theme: 'bootstrap4',
                            width: '100%',
                            placeholder: placeholder,
                            allowClear: true,
                            dropdownParent: $modal && $modal.length ? $modal : $(document.body),
                            language: {
                                noResults: function () { return 'Ничего не найдено'; },
                                searching: function () { return 'Поиск…'; }
                            }
                        });
                    }

                    if ($modal && $modal.length) {
                        $modal.on('shown.bs.modal', function () {
                            mount();
                            $select.val(null).trigger('change');
                            step = 1;
                            render();
                        });
                        $modal.on('hidden.bs.modal', function () {
                            if ($select.hasClass('select2-hidden-accessible')) {
                                $select.select2('destroy');
                            }
                        });
                    } else {
                        mount();
                    }
                }

                domainSelect.addEventListener('change', syncHints);
                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(domainSelect).on('change.select2', syncHints);
                }
                btnNext.addEventListener('click', function () {
                    if (step === 1 && !domainSelect.value) {
                        if (typeof window.jQuery !== 'undefined' && window.jQuery(domainSelect).data('select2')) {
                            window.jQuery(domainSelect).select2('open');
                        } else {
                            domainSelect.focus();
                        }
                        return;
                    }
                    if (step < max) {
                        step += 1;
                        if (step === 2) syncHints();
                        render();
                    }
                });
                btnPrev.addEventListener('click', function () {
                    if (step > 1) {
                        step -= 1;
                        render();
                    }
                });
                initDomainSelect2();
                render();
            })();
        </script>
    @endslot
@endcomponent
