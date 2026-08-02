@component('component.card', [
    'title' => __('Edit template') . ' · ' . $template->title,
    'documentTitle' => __('Edit template') . ' · ' . $template->title,
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    @endslot

    @php
        $orderKeys = \App\SeoReports\SeoReportSectionRegistry::orderedKeys($settings);
        $groupLabels = \App\SeoReports\SeoReportSectionRegistry::groupLabels();
        $brandPreview = \App\SeoReports\SeoReportBrandColor::normalize(
            old('brand_color', $template->brand_color) ?: '#1d4ed8'
        );
        $selectedKeys = [];
        $availableByGroup = [];
        foreach ($orderKeys as $key) {
            $meta = $sectionCatalog[$key] ?? null;
            if (!$meta || $key === 'cover') {
                continue;
            }
            if (!empty($toggles[$key])) {
                $selectedKeys[] = $key;
            } else {
                $group = $meta['group'] ?? 'core';
                $availableByGroup[$group][] = $key;
            }
        }
        foreach ($sectionCatalog as $key => $meta) {
            if ($key === 'cover' || in_array($key, $selectedKeys, true)) {
                continue;
            }
            $group = $meta['group'] ?? 'core';
            if (!isset($availableByGroup[$group]) || !in_array($key, $availableByGroup[$group], true)) {
                $availableByGroup[$group][] = $key;
            }
        }
        // Keep group order stable
        $orderedGroups = array_keys($groupLabels);
        $kpiHints = collect(\App\SeoReports\SeoReportKpiGoals::wizardRows())->keyBy('type');
    @endphp

    <div class="cabinet-sr-page">
        @include('pages.partials.seo-reports-nav', [
            'srTab' => 'templates',
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif

        <form method="post"
              action="{{ route('pages.seo-reports.templates.update', ['id' => $template->id]) }}"
              enctype="multipart/form-data"
              data-sr-tpl-form>
            @csrf
            <input type="hidden" name="sections[cover]" value="1">
            <input type="hidden" name="section_order[]" value="cover">

            <div class="cabinet-sr-tpl-hero">
                <div class="cabinet-sr-tpl-hero__main">
                    <a class="cabinet-sr-tpl-hero__back" href="{{ route('pages.seo-reports.templates') }}">← {{ __('Templates') }}</a>
                    <h1 class="cabinet-sr-hero__title">{{ __('Edit report template') }}</h1>
                    <p class="cabinet-sr-hero__lead mb-0">
                        {{ __('Report template edit lead') }}
                        @if(($projectsCount ?? 0) > 0)
                            · {{ __('Used in :count projects', ['count' => (int) $projectsCount]) }}
                        @endif
                    </p>
                </div>
                <div class="cabinet-sr-tpl-hero__actions">
                    <a class="btn btn-outline-secondary btn-sm"
                       href="{{ route('pages.seo-reports.templates.demo', ['id' => $template->id]) }}"
                       target="_blank" rel="noopener">{{ __('Open demo report') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save template') }}</button>
                </div>
            </div>

            <section class="cabinet-sr-tpl-basics">
                <div class="cabinet-sr-tpl-basics__fields">
                    <div>
                        <label class="form-label" for="srTplTitle">{{ __('Template name') }}</label>
                        <input type="text" class="form-control" id="srTplTitle" name="title"
                               value="{{ old('title', $template->title) }}" required maxlength="80"
                               data-sr-tpl-title>
                    </div>
                    <div>
                        <label class="form-label" for="srTplDesc">{{ __('Description') }}</label>
                        <input type="text" class="form-control" id="srTplDesc" name="description"
                               value="{{ old('description', $settings['description'] ?? '') }}"
                               maxlength="190"
                               placeholder="{{ __('Template description placeholder') }}">
                    </div>
                    <div>
                        <label class="form-label" for="srPeriod">{{ __('Default period') }}</label>
                        <select class="form-select" id="srPeriod" name="default_period">
                            <option value="prev_month" @if(($settings['default_period'] ?? '') === 'prev_month') selected @endif>
                                {{ __('Previous calendar month') }}
                            </option>
                            <option value="last_30" @if(($settings['default_period'] ?? '') === 'last_30') selected @endif>
                                {{ __('Last 30 days') }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="srTraffic">{{ __('Traffic filter') }}</label>
                        <select class="form-select" id="srTraffic" name="traffic_mode">
                            <option value="all" @if(($settings['traffic_mode'] ?? 'all') !== 'search_only') selected @endif>
                                {{ __('All channels') }}
                            </option>
                            <option value="search_only" @if(($settings['traffic_mode'] ?? '') === 'search_only') selected @endif>
                                {{ __('Search only') }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="cabinet-sr-tpl-basics__flags">
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="is_default" value="1"
                            @if(old('is_default', $template->is_default)) checked @endif>
                        <span>{{ __('Default template for new projects') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row mb-0">
                        <input type="checkbox" name="auto_compare" value="1"
                            @if(!empty($settings['auto_compare'])) checked @endif>
                        <span>{{ __('Auto compare previous period') }}</span>
                    </label>
                </div>
            </section>

            <section class="cabinet-sr-builder" data-sr-builder>
                <div class="cabinet-sr-builder__col" data-sr-builder-available-col>
                    <div class="cabinet-sr-builder__head">
                        <div>
                            <h2 class="cabinet-sr-builder__title">{{ __('Available blocks') }}</h2>
                            <p class="cabinet-sr-builder__hint">{{ __('Available blocks hint') }}</p>
                        </div>
                        <input type="search" class="form-control form-control-sm" data-sr-builder-search
                               placeholder="{{ __('Search blocks') }}" autocomplete="off">
                    </div>
                    <div class="cabinet-sr-builder__scroll" data-sr-available>
                        @foreach($orderedGroups as $groupKey)
                            @php $keys = $availableByGroup[$groupKey] ?? []; @endphp
                            <div class="cabinet-sr-builder__group" data-sr-group="{{ $groupKey }}"
                                 @if($keys === []) hidden @endif>
                                <div class="cabinet-sr-builder__group-title">{{ $groupLabels[$groupKey] ?? $groupKey }}</div>
                                @foreach($keys as $key)
                                    @php $meta = $sectionCatalog[$key]; @endphp
                                    <button type="button"
                                            class="cabinet-sr-builder__block"
                                            data-sr-block
                                            data-key="{{ $key }}"
                                            data-title="{{ $meta['title'] }}"
                                            data-group="{{ $meta['group'] }}"
                                            data-source="{{ $meta['source'] }}"
                                            data-source-label="{{ \App\SeoReports\SeoReportSectionRegistry::sourceLabel($meta['source']) }}"
                                            data-titlo="{{ ($meta['group'] ?? '') === 'titlo' ? '1' : '0' }}">
                                        <span class="cabinet-sr-builder__block-add" aria-hidden="true">+</span>
                                        <span class="cabinet-sr-builder__block-body">
                                            <span class="cabinet-sr-builder__block-title">{{ $meta['title'] }}</span>
                                            <span class="cabinet-sr-builder__block-meta">
                                                {{ \App\SeoReports\SeoReportSectionRegistry::sourceLabel($meta['source']) }}
                                                @if(($meta['group'] ?? '') === 'titlo')
                                                    · Titlo
                                                @endif
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                        <p class="cabinet-sr-builder__empty" data-sr-available-empty hidden>
                            {{ __('All blocks already in report') }}
                        </p>
                    </div>
                </div>

                <div class="cabinet-sr-builder__col cabinet-sr-builder__col--selected" data-sr-builder-selected-col>
                    <div class="cabinet-sr-builder__head">
                        <div>
                            <h2 class="cabinet-sr-builder__title">
                                {{ __('Selected blocks') }}
                                <span class="cabinet-sr-builder__count" data-sr-selected-count>{{ count($selectedKeys) }}</span>
                            </h2>
                            <p class="cabinet-sr-builder__hint">{{ __('Selected blocks hint') }}</p>
                        </div>
                    </div>
                    <div class="cabinet-sr-builder__scroll" data-sr-selected>
                        @foreach($selectedKeys as $key)
                            @php $meta = $sectionCatalog[$key]; @endphp
                            <div class="cabinet-sr-builder__picked"
                                 draggable="true"
                                 data-sr-picked
                                 data-key="{{ $key }}"
                                 data-title="{{ $meta['title'] }}"
                                 data-group="{{ $meta['group'] }}"
                                 data-source="{{ $meta['source'] }}"
                                 data-source-label="{{ \App\SeoReports\SeoReportSectionRegistry::sourceLabel($meta['source']) }}"
                                 data-titlo="{{ ($meta['group'] ?? '') === 'titlo' ? '1' : '0' }}">
                                <span class="cabinet-sr-builder__drag" aria-hidden="true">⋮⋮</span>
                                <input type="hidden" name="section_order[]" value="{{ $key }}">
                                <input type="hidden" name="sections[{{ $key }}]" value="1">
                                <span class="cabinet-sr-builder__block-body">
                                    <span class="cabinet-sr-builder__block-title">{{ $meta['title'] }}</span>
                                    <span class="cabinet-sr-builder__block-meta">
                                        {{ \App\SeoReports\SeoReportSectionRegistry::sourceLabel($meta['source']) }}
                                        @if(($meta['group'] ?? '') === 'titlo')
                                            · Titlo
                                        @endif
                                    </span>
                                </span>
                                <button type="button" class="cabinet-sr-builder__remove" data-sr-remove aria-label="{{ __('Remove') }}">×</button>
                            </div>
                        @endforeach
                        <p class="cabinet-sr-builder__empty" data-sr-selected-empty @if(count($selectedKeys) > 0) hidden @endif>
                            {{ __('Drag blocks here') }}
                        </p>
                    </div>
                </div>

                <aside class="cabinet-sr-builder__preview" data-sr-builder-preview>
                    <div class="cabinet-sr-builder__preview-card" style="--sr-accent: {{ $brandPreview }};">
                        <div class="cabinet-sr-builder__cover" data-sr-cover-preview>
                            <div class="cabinet-sr-builder__cover-accent"></div>
                            <div class="cabinet-sr-builder__cover-agency" data-sr-cover-agency>
                                {{ old('agency_name', $template->agency_name) ?: __('Your agency') }}
                            </div>
                            <div class="cabinet-sr-builder__cover-title" data-sr-cover-title>
                                {{ old('title', $template->title) }}
                            </div>
                            <div class="cabinet-sr-builder__cover-meta">{{ __('Live cover preview') }}</div>
                        </div>
                        <div class="cabinet-sr-builder__outline-label">{{ __('Report outline') }}</div>
                        <ol class="cabinet-sr-builder__outline" data-sr-outline>
                            @foreach($selectedKeys as $key)
                                <li>{{ $sectionCatalog[$key]['title'] ?? $key }}</li>
                            @endforeach
                        </ol>
                        <p class="cabinet-sr-builder__preview-note mb-0">{{ __('Template preview note') }}</p>
                    </div>
                </aside>
            </section>

            <details class="cabinet-sr-tpl-panel" open>
                <summary>{{ __('KPI goals') }}</summary>
                <div class="cabinet-sr-tpl-panel__body">
                    <p class="small text-secondary mb-2">{{ __('SEO report kpi settings hint') }}</p>
                    <div class="cabinet-sr-kpi-grid">
                        @foreach(($kpiGoals ?? []) as $goal)
                            @php $hint = $kpiHints[$goal['type']] ?? null; @endphp
                            <div class="cabinet-sr-kpi-card">
                                <label class="cabinet-sr-toggle-row mb-2">
                                    <input type="checkbox"
                                           name="kpi_goals[{{ $goal['type'] }}][enabled]"
                                           value="1"
                                        @if(!empty($goal['enabled'])) checked @endif>
                                    <span><strong>{{ $goal['label'] }}</strong></span>
                                </label>
                                <input type="number" min="0" step="1" class="form-control form-control-sm"
                                       name="kpi_goals[{{ $goal['type'] }}][target]"
                                       value="{{ $goal['target'] > 0 ? (int) $goal['target'] : '' }}"
                                       placeholder="{{ $hint['placeholder'] ?? __('Monthly target') }}">
                                @if($hint)
                                    <div class="form-text">{{ $hint['hint'] }} · {{ $hint['unit'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </details>

            <div class="cabinet-sr-settings-grid mt-3">
                <details class="cabinet-sr-tpl-panel" open>
                    <summary>{{ __('Agency branding') }}</summary>
                    <div class="cabinet-sr-tpl-panel__body">
                        <div class="mb-2">
                            <label class="form-label">{{ __('Agency name') }}</label>
                            <input type="text" class="form-control form-control-sm" name="agency_name"
                                   value="{{ old('agency_name', $template->agency_name) }}"
                                   data-sr-agency-name>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Address') }}</label>
                            <input type="text" class="form-control form-control-sm" name="agency_address"
                                   value="{{ old('agency_address', $template->agency_address) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" class="form-control form-control-sm" name="agency_email"
                                   value="{{ old('agency_email', $template->agency_email) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" class="form-control form-control-sm" name="agency_phone"
                                   value="{{ old('agency_phone', $template->agency_phone) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Brand color') }}</label>
                            <div class="cabinet-sr-color-row">
                                <input type="color" class="cabinet-sr-color-row__swatch" value="{{ $brandPreview }}"
                                       data-sr-brand-swatch aria-label="{{ __('Brand color') }}">
                                <input type="text" class="form-control form-control-sm" name="brand_color"
                                       placeholder="#1d4ed8"
                                       value="{{ old('brand_color', $template->brand_color) }}"
                                       data-sr-brand-color>
                            </div>
                        </div>
                        <label class="cabinet-sr-toggle-row">
                            <input type="checkbox" name="public_dark_theme" value="1"
                                @if(!empty($settings['public_dark_theme'])) checked @endif>
                            <span>{{ __('Dark theme for public link') }}</span>
                        </label>
                        <div class="mb-0 mt-2">
                            <label class="form-label">{{ __('Logo') }}</label>
                            @if($template->agencyLogoUrl())
                                <div class="mb-2">
                                    <img src="{{ $template->agencyLogoUrl() }}" alt="" style="max-height:40px">
                                </div>
                                <label class="cabinet-sr-toggle-row mb-2">
                                    <input type="checkbox" name="clear_agency_logo" value="1">
                                    <span>{{ __('Remove logo') }}</span>
                                </label>
                            @endif
                            <input type="file" class="form-control form-control-sm" name="agency_logo" accept="image/*">
                        </div>
                    </div>
                </details>

                <details class="cabinet-sr-tpl-panel" open>
                    <summary>{{ __('Your manager') }}</summary>
                    <div class="cabinet-sr-tpl-panel__body">
                        <div class="mb-2">
                            <label class="form-label">{{ __('Name') }}</label>
                            <input type="text" class="form-control form-control-sm" name="manager_name"
                                   value="{{ old('manager_name', $template->manager_name) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" class="form-control form-control-sm" name="manager_phone"
                                   value="{{ old('manager_phone', $template->manager_phone) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" class="form-control form-control-sm" name="manager_email"
                                   value="{{ old('manager_email', $template->manager_email) }}">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">{{ __('Avatar') }}</label>
                            @if($template->managerAvatarUrl())
                                <div class="mb-2">
                                    <img src="{{ $template->managerAvatarUrl() }}" alt="" class="cabinet-sr-cover__avatar">
                                </div>
                                <label class="cabinet-sr-toggle-row mb-2">
                                    <input type="checkbox" name="clear_manager_avatar" value="1">
                                    <span>{{ __('Remove avatar') }}</span>
                                </label>
                            @endif
                            <input type="file" class="form-control form-control-sm" name="manager_avatar" accept="image/*">
                        </div>
                    </div>
                </details>
            </div>

            <details class="cabinet-sr-tpl-panel mt-3">
                <summary>{{ __('Automation') }}</summary>
                <div class="cabinet-sr-tpl-panel__body cabinet-sr-tpl-panel__body--row">
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="auto_generate" value="1"
                            @if(!empty($settings['auto_generate'])) checked @endif>
                        <span>{{ __('Auto-generate monthly report') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="remind_missing" value="1"
                            @if(!empty($settings['remind_missing'])) checked @endif>
                        <span>{{ __('Remind if monthly report missing') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="confirmed_sources_only" value="1"
                            @if(!empty($settings['confirmed_sources_only'])) checked @endif>
                        <span>{{ __('Confirmed sources only') }}</span>
                    </label>
                    <label class="cabinet-sr-toggle-row">
                        <input type="checkbox" name="enable_ai_summary" value="1"
                            @if(!empty($settings['enable_ai_summary'])) checked @endif>
                        <span>{{ __('Enable AI summary') }}</span>
                    </label>
                </div>
            </details>

            <div class="cabinet-sr-tpl-sticky">
                <div class="cabinet-sr-tpl-sticky__meta">
                    <span data-sr-sticky-count>{{ count($selectedKeys) }}</span> {{ __('blocks in report') }}
                    @if(($projectsCount ?? 0) > 0)
                        · {{ __('Applies to :count projects', ['count' => (int) $projectsCount]) }}
                    @endif
                </div>
                <div class="cabinet-sr-tpl-sticky__actions">
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('pages.seo-reports.templates') }}">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save template') }}</button>
                </div>
            </div>
        </form>
    </div>

    @slot('js')
        <script>
            (function () {
                var builder = document.querySelector('[data-sr-builder]');
                if (!builder) return;

                var available = builder.querySelector('[data-sr-available]');
                var selected = builder.querySelector('[data-sr-selected]');
                var search = builder.querySelector('[data-sr-builder-search]');
                var outline = builder.querySelector('[data-sr-outline]');
                var countEls = document.querySelectorAll('[data-sr-selected-count], [data-sr-sticky-count]');
                var availableEmpty = builder.querySelector('[data-sr-available-empty]');
                var selectedEmpty = builder.querySelector('[data-sr-selected-empty]');
                var groupLabels = @json($groupLabels);
                var dragEl = null;

                function ensureGroup(groupKey) {
                    var group = available.querySelector('[data-sr-group="' + groupKey + '"]');
                    if (group) return group;
                    group = document.createElement('div');
                    group.className = 'cabinet-sr-builder__group';
                    group.setAttribute('data-sr-group', groupKey);
                    var title = document.createElement('div');
                    title.className = 'cabinet-sr-builder__group-title';
                    title.textContent = groupLabels[groupKey] || groupKey;
                    group.appendChild(title);
                    available.insertBefore(group, availableEmpty);
                    return group;
                }

                function syncUi() {
                    var picked = selected.querySelectorAll('[data-sr-picked]');
                    countEls.forEach(function (el) { el.textContent = String(picked.length); });
                    if (selectedEmpty) selectedEmpty.hidden = picked.length > 0;
                    if (outline) {
                        outline.innerHTML = '';
                        picked.forEach(function (item) {
                            var li = document.createElement('li');
                            li.textContent = item.getAttribute('data-title') || '';
                            outline.appendChild(li);
                        });
                    }
                    available.querySelectorAll('[data-sr-group]').forEach(function (group) {
                        var blocks = group.querySelectorAll('[data-sr-block]');
                        var visible = 0;
                        blocks.forEach(function (b) {
                            if (!b.hidden) visible += 1;
                        });
                        group.hidden = visible === 0;
                    });
                    var anyAvail = available.querySelectorAll('[data-sr-block]:not([hidden])').length > 0;
                    if (availableEmpty) availableEmpty.hidden = anyAvail;
                }

                function makePicked(from) {
                    var key = from.getAttribute('data-key');
                    var title = from.getAttribute('data-title') || key;
                    var sourceLabel = from.getAttribute('data-source-label') || '';
                    var titlo = from.getAttribute('data-titlo') === '1';
                    var el = document.createElement('div');
                    el.className = 'cabinet-sr-builder__picked';
                    el.draggable = true;
                    el.setAttribute('data-sr-picked', '');
                    ['key', 'title', 'group', 'source', 'source-label', 'titlo'].forEach(function (attr) {
                        var val = from.getAttribute('data-' + attr);
                        if (val != null) el.setAttribute('data-' + attr, val);
                    });
                    el.innerHTML =
                        '<span class="cabinet-sr-builder__drag" aria-hidden="true">⋮⋮</span>' +
                        '<input type="hidden" name="section_order[]" value="' + key + '">' +
                        '<input type="hidden" name="sections[' + key + ']" value="1">' +
                        '<span class="cabinet-sr-builder__block-body">' +
                            '<span class="cabinet-sr-builder__block-title"></span>' +
                            '<span class="cabinet-sr-builder__block-meta"></span>' +
                        '</span>' +
                        '<button type="button" class="cabinet-sr-builder__remove" data-sr-remove aria-label="Remove">×</button>';
                    el.querySelector('.cabinet-sr-builder__block-title').textContent = title;
                    el.querySelector('.cabinet-sr-builder__block-meta').textContent =
                        sourceLabel + (titlo ? ' · Titlo' : '');
                    return el;
                }

                function makeAvailable(from) {
                    var key = from.getAttribute('data-key');
                    var title = from.getAttribute('data-title') || key;
                    var sourceLabel = from.getAttribute('data-source-label') || '';
                    var titlo = from.getAttribute('data-titlo') === '1';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'cabinet-sr-builder__block';
                    btn.setAttribute('data-sr-block', '');
                    ['key', 'title', 'group', 'source', 'source-label', 'titlo'].forEach(function (attr) {
                        var val = from.getAttribute('data-' + attr);
                        if (val != null) btn.setAttribute('data-' + attr, val);
                    });
                    btn.innerHTML =
                        '<span class="cabinet-sr-builder__block-add" aria-hidden="true">+</span>' +
                        '<span class="cabinet-sr-builder__block-body">' +
                            '<span class="cabinet-sr-builder__block-title"></span>' +
                            '<span class="cabinet-sr-builder__block-meta"></span>' +
                        '</span>';
                    btn.querySelector('.cabinet-sr-builder__block-title').textContent = title;
                    btn.querySelector('.cabinet-sr-builder__block-meta').textContent =
                        sourceLabel + (titlo ? ' · Titlo' : '');
                    return btn;
                }

                function addBlock(blockEl) {
                    if (!blockEl || !blockEl.getAttribute('data-key')) return;
                    var key = blockEl.getAttribute('data-key');
                    if (selected.querySelector('[data-sr-picked][data-key="' + key + '"]')) return;
                    var picked = makePicked(blockEl);
                    selected.insertBefore(picked, selectedEmpty);
                    blockEl.parentNode && blockEl.parentNode.removeChild(blockEl);
                    bindDrag(picked);
                    syncUi();
                    filterAvailable();
                }

                function removeBlock(pickedEl) {
                    if (!pickedEl) return;
                    var btn = makeAvailable(pickedEl);
                    var group = ensureGroup(btn.getAttribute('data-group') || 'core');
                    group.appendChild(btn);
                    pickedEl.parentNode && pickedEl.parentNode.removeChild(pickedEl);
                    syncUi();
                    filterAvailable();
                }

                function filterAvailable() {
                    var q = ((search && search.value) || '').toLowerCase().trim();
                    available.querySelectorAll('[data-sr-block]').forEach(function (b) {
                        var title = (b.getAttribute('data-title') || '').toLowerCase();
                        var source = (b.getAttribute('data-source-label') || '').toLowerCase();
                        b.hidden = !!(q && title.indexOf(q) === -1 && source.indexOf(q) === -1);
                    });
                    syncUi();
                }

                function bindDrag(el) {
                    el.addEventListener('dragstart', function (e) {
                        dragEl = el;
                        el.classList.add('is-dragging');
                        if (e.dataTransfer) {
                            e.dataTransfer.effectAllowed = 'move';
                            e.dataTransfer.setData('text/plain', el.getAttribute('data-key') || '');
                        }
                    });
                    el.addEventListener('dragend', function () {
                        el.classList.remove('is-dragging');
                        selected.querySelectorAll('.is-drop-target').forEach(function (n) {
                            n.classList.remove('is-drop-target');
                        });
                        dragEl = null;
                    });
                }

                available.addEventListener('click', function (e) {
                    var block = e.target.closest('[data-sr-block]');
                    if (block) addBlock(block);
                });

                selected.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-sr-remove]');
                    if (!btn) return;
                    removeBlock(btn.closest('[data-sr-picked]'));
                });

                selected.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    var over = e.target.closest('[data-sr-picked]');
                    selected.querySelectorAll('.is-drop-target').forEach(function (n) {
                        n.classList.remove('is-drop-target');
                    });
                    if (over && dragEl && over !== dragEl) {
                        over.classList.add('is-drop-target');
                        var rect = over.getBoundingClientRect();
                        var before = (e.clientY - rect.top) < rect.height / 2;
                        if (before) selected.insertBefore(dragEl, over);
                        else selected.insertBefore(dragEl, over.nextSibling);
                    }
                });

                selected.addEventListener('drop', function (e) {
                    e.preventDefault();
                    syncUi();
                });

                if (search) {
                    search.addEventListener('input', filterAvailable);
                }

                selected.querySelectorAll('[data-sr-picked]').forEach(bindDrag);

                var titleInput = document.querySelector('[data-sr-tpl-title]');
                var agencyInput = document.querySelector('[data-sr-agency-name]');
                var coverTitle = document.querySelector('[data-sr-cover-title]');
                var coverAgency = document.querySelector('[data-sr-cover-agency]');
                var brandColor = document.querySelector('[data-sr-brand-color]');
                var brandSwatch = document.querySelector('[data-sr-brand-swatch]');
                var previewCard = document.querySelector('[data-sr-builder-preview] .cabinet-sr-builder__preview-card');

                function syncCover() {
                    if (coverTitle && titleInput) coverTitle.textContent = titleInput.value || '—';
                    if (coverAgency && agencyInput) {
                        coverAgency.textContent = agencyInput.value || @json(__('Your agency'));
                    }
                }
                if (titleInput) titleInput.addEventListener('input', syncCover);
                if (agencyInput) agencyInput.addEventListener('input', syncCover);

                function syncBrand(val) {
                    var v = (val || '').trim();
                    if (!/^#?[0-9a-fA-F]{6}$/.test(v)) return;
                    if (v.charAt(0) !== '#') v = '#' + v;
                    if (previewCard) previewCard.style.setProperty('--sr-accent', v);
                    if (brandSwatch && brandSwatch.value.toLowerCase() !== v.toLowerCase()) brandSwatch.value = v;
                    if (brandColor && brandColor.value !== v) brandColor.value = v;
                }
                if (brandColor) brandColor.addEventListener('input', function () { syncBrand(brandColor.value); });
                if (brandSwatch) brandSwatch.addEventListener('input', function () { syncBrand(brandSwatch.value); });

                syncUi();
            })();
        </script>
    @endslot
@endcomponent
