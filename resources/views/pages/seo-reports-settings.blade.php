@component('component.card', [
    'title' => __('Settings') . ' · ' . $project->domain,
    'documentTitle' => __('Settings') . ' · ' . $project->domain,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    @php
        $hasTemplate = (int) ($project->template_id ?? 0) > 0;
        $hasMetrika = (int) ($project->metrika_counter_id ?? 0) > 0;
        $hasMonitoring = (int) ($project->monitoring_project_id ?? 0) > 0;
        $hasGsc = trim((string) ($settings['gsc_property'] ?? '')) !== '';
        $hasWm = trim((string) ($settings['webmaster_host'] ?? '')) !== '';
        $hasAds = trim((string) ($settings['vk_ads_token'] ?? '')) !== ''
            || trim((string) ($settings['meta_ads_token'] ?? '')) !== ''
            || trim((string) ($settings['vk_smm_token'] ?? '')) !== '';
        $hasEmail = !empty($settings['auto_email']) || trim((string) ($settings['auto_email_to'] ?? '')) !== '';

        $stepKinds = [
            1 => trim((string) ($project->title ?? '')) !== '' ? 'done' : 'needed',
            2 => $hasTemplate ? 'done' : 'needed',
            3 => ($hasMetrika || $hasMonitoring) ? 'done' : 'optional',
            4 => ($hasGsc || $hasWm) ? 'done' : 'optional',
            5 => $hasAds ? 'done' : 'optional',
            6 => $hasEmail ? 'done' : 'optional',
        ];
        $stepLabels = [
            'done' => __('Step done'),
            'needed' => __('Step needed'),
            'optional' => __('Step optional'),
        ];
        $openStep = 1;
        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            if (($stepKinds[$n] ?? '') === 'needed') {
                $openStep = $n;
                break;
            }
        }
    @endphp

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'settings',
            'srContextProject' => $project,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif

        <div class="cabinet-sr-hero">
            <div>
                <h1 class="cabinet-sr-hero__title">{{ __('Project settings') }}</h1>
                <p class="cabinet-sr-hero__lead">{{ __('SEO report project settings steps lead') }}</p>
            </div>
        </div>

        <form method="post"
              id="sr-settings-form"
              class="cabinet-sr-steps"
              action="{{ route('pages.seo-reports.settings.update', ['id' => $project->id]) }}"
              data-sr-settings-steps>
            @csrf

            <details class="cabinet-sr-step" data-sr-step="1" @if($openStep === 1) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">1</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Project') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step project hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[1] }}">{{ $stepLabels[$stepKinds[1]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label" for="srTitle">{{ __('Title') }}</label>
                        <input type="text" class="form-control form-control-sm" id="srTitle" name="title"
                               value="{{ old('title', $project->title) }}">
                        <div class="form-text">{{ __('Project title hint') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Mirror domains') }}</label>
                        <input type="text" class="form-control form-control-sm" name="mirror_domains"
                               value="{{ old('mirror_domains', implode(', ', $settings['mirror_domains'] ?? [])) }}"
                               placeholder="www.example.com, m.example.com">
                        <div class="form-text">{{ __('Mirror domains hint') }}</div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="2">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="2" @if($openStep === 2) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">2</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Report template') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Attach report template hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[2] }}">{{ $stepLabels[$stepKinds[2]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label" for="srTemplateId">{{ __('Template') }}</label>
                        <select class="form-select form-select-sm" id="srTemplateId" name="template_id" required>
                            @foreach($templates as $tpl)
                                <option value="{{ $tpl->id }}"
                                    @if((int) $project->template_id === (int) $tpl->id) selected @endif>
                                    {{ $tpl->title }}
                                    @if($tpl->is_default) · {{ __('Default') }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($attachedTemplate)
                        <p class="small mb-2">
                            <a href="{{ route('pages.seo-reports.templates.edit', ['id' => $attachedTemplate->id]) }}">
                                {{ __('Edit template') }}: {{ $attachedTemplate->title }}
                            </a>
                        </p>
                        <p class="form-text mb-3">{{ __('Template change affects all linked projects') }}</p>
                    @endif
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="3">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="3" @if($openStep === 3) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">3</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Integrations') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step integrations hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[3] }}">{{ $stepLabels[$stepKinds[3]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label">{{ __('Yandex Metrika') }}</label>
                        <select class="form-select form-select-sm" name="metrika_counter_id" data-sr-select2>
                            <option value="">{{ __('Not connected') }}</option>
                            @foreach($metrikaBindings as $binding)
                                <option value="{{ $binding->counter_id }}"
                                    @if((int) $project->metrika_counter_id === (int) $binding->counter_id) selected @endif>
                                    @if($binding->counter_name){{ $binding->counter_name }} · @endif{{ $binding->domain }}
                                    · #{{ $binding->counter_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Position monitoring') }}</label>
                        <select class="form-select form-select-sm" name="monitoring_project_id" data-sr-select2>
                            <option value="">{{ __('Not connected') }}</option>
                            @foreach($monitoringOptions as $option)
                                <option value="{{ $option['id'] }}"
                                    @if((int) $project->monitoring_project_id === (int) $option['id']) selected @endif>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Metrika goals') }}</label>
                        @if(empty($metrikaGoals))
                            <div class="form-text">{{ __('Connect Metrika counter to load goals') }}</div>
                        @else
                            <div class="cabinet-sr-goals">
                                @foreach($metrikaGoals as $goal)
                                    <label class="cabinet-sr-toggle-row">
                                        <input type="checkbox"
                                               name="metrika_goal_ids[]"
                                               value="{{ $goal['id'] }}"
                                            @if(in_array((int) $goal['id'], $selectedGoalIds, true)) checked @endif>
                                        <span>{{ $goal['name'] }} <span class="text-secondary">#{{ $goal['id'] }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="4">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="4" @if($openStep === 4) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">4</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Search consoles') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step consoles hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[4] }}">{{ $stepLabels[$stepKinds[4]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="mb-2">
                        <label class="form-label">Google Search Console property</label>
                        <input type="text" class="form-control form-control-sm" name="gsc_property"
                               value="{{ old('gsc_property', $settings['gsc_property'] ?? '') }}"
                               placeholder="sc-domain:example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Yandex Webmaster host') }}</label>
                        <input type="text" class="form-control form-control-sm" name="webmaster_host"
                               value="{{ old('webmaster_host', $settings['webmaster_host'] ?? '') }}"
                               placeholder="https:example.com:443">
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="5">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="5" @if($openStep === 5) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">5</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('VK / Meta ads & community') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('VK Meta API token hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[5] }}">{{ $stepLabels[$stepKinds[5]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <div class="cabinet-sr-step__grid mb-3">
                        <div>
                            <label class="form-label">{{ __('VK Ads token') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" name="vk_ads_token"
                                   value="{{ old('vk_ads_token', $settings['vk_ads_token'] ?? '') }}" autocomplete="off">
                            <label class="form-label">{{ __('VK Ads account') }}</label>
                            <input type="text" class="form-control form-control-sm" name="vk_ads_account"
                                   value="{{ old('vk_ads_account', $settings['vk_ads_account'] ?? '') }}">
                        </div>
                        <div>
                            <label class="form-label">{{ __('Meta Ads token') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" name="meta_ads_token"
                                   value="{{ old('meta_ads_token', $settings['meta_ads_token'] ?? '') }}" autocomplete="off">
                            <label class="form-label">{{ __('Meta Ads account') }}</label>
                            <input type="text" class="form-control form-control-sm" name="meta_ads_account"
                                   value="{{ old('meta_ads_account', $settings['meta_ads_account'] ?? '') }}">
                        </div>
                        <div>
                            <label class="form-label">{{ __('VK community token') }}</label>
                            <input type="text" class="form-control form-control-sm mb-2" name="vk_smm_token"
                                   value="{{ old('vk_smm_token', $settings['vk_smm_token'] ?? '') }}" autocomplete="off">
                            <label class="form-label">{{ __('VK community group ID') }}</label>
                            <input type="text" class="form-control form-control-sm" name="vk_smm_group_id"
                                   value="{{ old('vk_smm_group_id', $settings['vk_smm_group_id'] ?? '') }}"
                                   placeholder="123456789">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-sr-step-next="6">{{ __('Next step') }}</button>
                </div>
            </details>

            <details class="cabinet-sr-step" data-sr-step="6" @if($openStep === 6) open @endif>
                <summary class="cabinet-sr-step__summary">
                    <span class="cabinet-sr-step__num">6</span>
                    <span class="cabinet-sr-step__text">
                        <span class="cabinet-sr-step__title">{{ __('Client email') }}</span>
                        <span class="cabinet-sr-step__hint">{{ __('Settings step email hint') }}</span>
                    </span>
                    <span class="cabinet-sr-step__status is-{{ $stepKinds[6] }}">{{ $stepLabels[$stepKinds[6]] }}</span>
                </summary>
                <div class="cabinet-sr-step__body">
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="auto_email" value="1"
                            @if(!empty($settings['auto_email'])) checked @endif>
                        <span>{{ __('Email report after auto-generate') }}</span>
                    </label>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Email recipients') }}</label>
                        <input type="text" class="form-control form-control-sm" name="auto_email_to"
                               value="{{ old('auto_email_to', $settings['auto_email_to'] ?? '') }}"
                               placeholder="client@example.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('Email message') }}</label>
                        <textarea class="form-control form-control-sm" name="auto_email_message" rows="2">{{ old('auto_email_message', $settings['auto_email_message'] ?? '') }}</textarea>
                    </div>
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="auto_email_cc_manager" value="1"
                            @if(!empty($settings['auto_email_cc_manager'])) checked @endif>
                        <span>{{ __('CC manager') }}</span>
                    </label>
                </div>
            </details>

            <div class="cabinet-sr-steps__actions">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('pages.seo-reports.show', ['id' => $project->id]) }}">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script>
            (function () {
                if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 === 'function') {
                    window.jQuery('[data-sr-select2]').each(function () {
                        var $el = window.jQuery(this);
                        var realOptions = $el.find('option').filter(function () {
                            return String(this.value || '') !== '';
                        }).length;
                        if (realOptions < 10) return;
                        $el.select2({
                            theme: 'bootstrap4',
                            width: '100%',
                            allowClear: true,
                            placeholder: $el.find('option[value=""]').first().text() || ''
                        });
                    });
                }

                var root = document.querySelector('[data-sr-settings-steps]');
                if (!root) return;
                var steps = Array.prototype.slice.call(root.querySelectorAll('[data-sr-step]'));

                function openStep(n) {
                    steps.forEach(function (el) {
                        var id = el.getAttribute('data-sr-step');
                        el.open = String(id) === String(n);
                    });
                    var target = root.querySelector('[data-sr-step="' + n + '"]');
                    if (target && typeof target.scrollIntoView === 'function') {
                        target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                }

                // Аккордеон: при открытии шага остальные сворачиваются
                steps.forEach(function (el) {
                    el.addEventListener('toggle', function () {
                        if (!el.open) return;
                        steps.forEach(function (other) {
                            if (other !== el) other.open = false;
                        });
                    });
                });

                root.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-sr-step-next]');
                    if (!btn) return;
                    e.preventDefault();
                    openStep(btn.getAttribute('data-sr-step-next'));
                });
            })();
        </script>
    @endslot
@endcomponent
