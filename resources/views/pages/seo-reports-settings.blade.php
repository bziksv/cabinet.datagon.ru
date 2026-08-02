@component('component.card', [
    'title' => __('Settings') . ' · ' . $project->domain,
    'documentTitle' => __('Settings') . ' · ' . $project->domain,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

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
                <p class="cabinet-sr-hero__lead">{{ __('SEO report project settings lead') }}</p>
            </div>
        </div>

        <form method="post"
              id="sr-settings-form"
              action="{{ route('pages.seo-reports.settings.update', ['id' => $project->id]) }}">
            @csrf
            <div class="cabinet-sr-settings-grid">
                <fieldset class="cabinet-sr-fieldset">
                    <legend>{{ __('Report template') }}</legend>
                    <p class="small text-secondary mb-2">{{ __('Attach report template hint') }}</p>
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
                        <p class="form-text mb-0">{{ __('Template change affects all linked projects') }}</p>
                    @endif
                </fieldset>

                <fieldset class="cabinet-sr-fieldset">
                    <legend>{{ __('Project') }}</legend>
                    <div class="mb-2">
                        <label class="form-label" for="srTitle">{{ __('Title') }}</label>
                        <input type="text" class="form-control form-control-sm" id="srTitle" name="title"
                               value="{{ old('title', $project->title) }}">
                        <div class="form-text">{{ __('Project title hint') }}</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">{{ __('Mirror domains') }}</label>
                        <input type="text" class="form-control form-control-sm" name="mirror_domains"
                               value="{{ old('mirror_domains', implode(', ', $settings['mirror_domains'] ?? [])) }}"
                               placeholder="www.example.com, m.example.com">
                        <div class="form-text">{{ __('Mirror domains hint') }}</div>
                    </div>
                </fieldset>

                <fieldset class="cabinet-sr-fieldset">
                    <legend>{{ __('Integrations') }}</legend>
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
                    <div class="mb-0">
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
                </fieldset>

                <fieldset class="cabinet-sr-fieldset">
                    <legend>{{ __('Client email') }}</legend>
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
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="auto_email_cc_manager" value="1"
                            @if(!empty($settings['auto_email_cc_manager'])) checked @endif>
                        <span>{{ __('CC manager') }}</span>
                    </label>
                </fieldset>

                <fieldset class="cabinet-sr-fieldset">
                    <legend>{{ __('Search consoles') }}</legend>
                    <div class="mb-2">
                        <label class="form-label">Google Search Console property</label>
                        <input type="text" class="form-control form-control-sm" name="gsc_property"
                               value="{{ old('gsc_property', $settings['gsc_property'] ?? '') }}"
                               placeholder="sc-domain:example.com">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">{{ __('Yandex Webmaster host') }}</label>
                        <input type="text" class="form-control form-control-sm" name="webmaster_host"
                               value="{{ old('webmaster_host', $settings['webmaster_host'] ?? '') }}"
                               placeholder="https:example.com:443">
                    </div>
                </fieldset>
            </div>

            <fieldset class="cabinet-sr-fieldset mt-3">
                <legend>{{ __('VK / Meta ads & community') }}</legend>
                <p class="small text-secondary mb-2">{{ __('VK Meta CSV token hint') }}</p>
                <div class="cabinet-sr-settings-grid">
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
            </fieldset>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Save') }}</button>
                <a class="btn btn-outline-secondary btn-sm"
                   href="{{ route('pages.seo-reports.show', ['id' => $project->id]) }}">{{ __('Cancel') }}</a>
            </div>
        </form>

        <div class="cabinet-sr-settings-grid mt-3">
            <fieldset class="cabinet-sr-fieldset">
                <legend>{{ __('Import GSC CSV') }}</legend>
                <form method="post" action="{{ route('pages.seo-reports.settings.import-console', ['id' => $project->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source" value="gsc">
                    <input type="file" class="form-control form-control-sm mb-2" name="csv" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Upload') }}</button>
                </form>
            </fieldset>
            <fieldset class="cabinet-sr-fieldset">
                <legend>{{ __('Import Webmaster CSV') }}</legend>
                <form method="post" action="{{ route('pages.seo-reports.settings.import-console', ['id' => $project->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source" value="webmaster">
                    <input type="file" class="form-control form-control-sm mb-2" name="csv" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Upload') }}</button>
                </form>
            </fieldset>
            <fieldset class="cabinet-sr-fieldset">
                <legend>{{ __('Import VK Ads CSV') }}</legend>
                <form method="post" action="{{ route('pages.seo-reports.settings.import-ads', ['id' => $project->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source" value="vk_ads">
                    <input type="file" class="form-control form-control-sm mb-2" name="csv" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Upload') }}</button>
                </form>
            </fieldset>
            <fieldset class="cabinet-sr-fieldset">
                <legend>{{ __('Import Meta Ads CSV') }}</legend>
                <form method="post" action="{{ route('pages.seo-reports.settings.import-ads', ['id' => $project->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source" value="meta_ads">
                    <input type="file" class="form-control form-control-sm mb-2" name="csv" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Upload') }}</button>
                </form>
            </fieldset>
            <fieldset class="cabinet-sr-fieldset">
                <legend>{{ __('Import VK community CSV') }}</legend>
                <form method="post" action="{{ route('pages.seo-reports.settings.import-ads', ['id' => $project->id]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="source" value="vk_smm">
                    <input type="file" class="form-control form-control-sm mb-2" name="csv" accept=".csv,text/csv" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Upload') }}</button>
                </form>
            </fieldset>
        </div>
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
            })();
        </script>
    @endslot
@endcomponent
