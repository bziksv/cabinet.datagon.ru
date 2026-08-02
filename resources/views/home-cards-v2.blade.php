@extends('layouts.app')

@section('title', __('Main page'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-home.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-home-cards-v2.css') }}?v=20260801-modules-collapse1">
@endsection

@section('content')
    <div class="cabinet-home-page cabinet-home-cards-v2-page">
        @include('home.partials.hero', ['summary' => $summary])
        @include('home.partials.stats', ['summary' => $summary])
        @include('home.partials.seo-checklist-due', ['seoChecklistDue' => $seoChecklistDue ?? null])
        @include('home-cards-v2.partials.sites', ['userSites' => $userSites ?? []])
        @include('home-cards-v2.partials.modules', ['modules' => $modules])
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var moduleInput = document.getElementById('cabinet-home-module-search');
            if (moduleInput) {
                var cards = document.querySelectorAll('[data-cabinet-module-title]');
                moduleInput.addEventListener('input', function () {
                    var q = moduleInput.value.trim().toLowerCase();
                    var visible = 0;
                    cards.forEach(function (col) {
                        var title = (col.getAttribute('data-cabinet-module-title') || '').toLowerCase();
                        var match = q === '' || title.indexOf(q) !== -1;
                        col.classList.toggle('is-hidden', !match);
                        if (match) {
                            visible++;
                        }
                    });
                    var empty = document.getElementById('cabinet-home-modules-empty');
                    if (empty) {
                        empty.classList.toggle('d-none', visible > 0 || q === '');
                    }
                });
            }

            var sitesSection = document.getElementById('cabinet-home-sites');
            var sitesToggle = document.getElementById('cabinet-home-sites-toggle');
            if (sitesSection && sitesToggle) {
                var sitesStorageKey = 'cabinet-home-sites-collapsed';
                try {
                    if (localStorage.getItem(sitesStorageKey) === '1') {
                        sitesSection.classList.add('is-collapsed');
                        sitesToggle.setAttribute('aria-expanded', 'false');
                    }
                } catch (e) {}
                sitesToggle.addEventListener('click', function () {
                    var collapsed = sitesSection.classList.toggle('is-collapsed');
                    sitesToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    try {
                        localStorage.setItem(sitesStorageKey, collapsed ? '1' : '0');
                    } catch (e) {}
                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                });
            }

            var modulesSection = document.getElementById('cabinet-home-modules');
            var modulesToggle = document.getElementById('cabinet-home-modules-toggle');
            if (modulesSection && modulesToggle) {
                var modulesStorageKey = 'cabinet-home-modules-collapsed';
                try {
                    if (localStorage.getItem(modulesStorageKey) === '1') {
                        modulesSection.classList.add('is-collapsed');
                        modulesToggle.setAttribute('aria-expanded', 'false');
                    }
                } catch (e) {}
                modulesToggle.addEventListener('click', function () {
                    var collapsed = modulesSection.classList.toggle('is-collapsed');
                    modulesToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    try {
                        localStorage.setItem(modulesStorageKey, collapsed ? '1' : '0');
                    } catch (e) {}
                });
            }

            if (sitesSection) {
                var sitesMode = 'active';
                var metrikaFilterOff = false;
                var sitesPage = 1;
                var sitesSortKey = 'domain';
                var sitesSortDir = 'asc';
                var modeButtons = sitesSection.querySelectorAll('[data-sites-mode]');
                var sitesInput = document.getElementById('cabinet-home-sites-search');
                var metrikaFilterBtn = sitesSection.querySelector('[data-sites-filter-metrika="off"]');
                var pagerEl = sitesSection.querySelector('[data-sites-pager]');
                var pagerInfoEl = pagerEl ? pagerEl.querySelector('[data-sites-pager-info]') : null;
                var pagerLabelEl = pagerEl ? pagerEl.querySelector('[data-sites-pager-label]') : null;
                var pagerPrevBtn = pagerEl ? pagerEl.querySelector('[data-sites-page="prev"]') : null;
                var pagerNextBtn = pagerEl ? pagerEl.querySelector('[data-sites-page="next"]') : null;
                var pageSize = pagerEl ? (parseInt(pagerEl.getAttribute('data-page-size'), 10) || 50) : 50;
                var csrf = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrf ? csrf.getAttribute('content') : '';
                var pagerInfoTpl = @json(__('Sites page range'));
                var pagerLabelTpl = @json(__('Page of pages'));

                function activePanel() {
                    return sitesSection.querySelector('[data-sites-panel="' + sitesMode + '"]');
                }

                function formatPagerText(tpl, map) {
                    return String(tpl || '').replace(/:([a-z_]+)/gi, function (_, key) {
                        return map[key] != null ? String(map[key]) : '';
                    });
                }

                function syncSitesSortHeaders() {
                    document.querySelectorAll('[data-sites-sort]').forEach(function (th) {
                        var key = th.getAttribute('data-sites-sort');
                        var active = key === sitesSortKey;
                        th.classList.toggle('is-sorted', active);
                        th.classList.toggle('is-sorted-asc', active && sitesSortDir === 'asc');
                        th.classList.toggle('is-sorted-desc', active && sitesSortDir === 'desc');
                        th.setAttribute('aria-sort', active ? (sitesSortDir === 'asc' ? 'ascending' : 'descending') : 'none');
                        var icon = th.querySelector('.cabinet-home-sites-sort-btn > .bi');
                        if (icon) {
                            icon.className = 'bi ' + (
                                !active ? 'bi-arrow-down-up' :
                                    (sitesSortDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
                            );
                        }
                    });
                }

                function sortSitesRows(rows) {
                    if (!sitesSortKey) {
                        return rows;
                    }
                    var key = sitesSortKey;
                    var dir = sitesSortDir === 'desc' ? -1 : 1;
                    var numeric = key.indexOf('visits_') === 0 || key.indexOf('mod-') === 0 || key === 'modules';
                    return rows.slice().sort(function (a, b) {
                        var av = a.getAttribute('data-sort-' + key);
                        var bv = b.getAttribute('data-sort-' + key);
                        if (av == null) av = '';
                        if (bv == null) bv = '';
                        if (numeric) {
                            var an = av === '' ? -1 : parseFloat(av);
                            var bn = bv === '' ? -1 : parseFloat(bv);
                            if (isNaN(an)) an = -1;
                            if (isNaN(bn)) bn = -1;
                            if (an === bn) {
                                return (a.getAttribute('data-sort-domain') || '').localeCompare(
                                    b.getAttribute('data-sort-domain') || '',
                                    'ru',
                                    { sensitivity: 'base' }
                                );
                            }
                            return (an - bn) * dir;
                        }
                        var cmp = String(av).localeCompare(String(bv), 'ru', { sensitivity: 'base' });
                        if (cmp === 0) {
                            return (a.getAttribute('data-sort-domain') || '').localeCompare(
                                b.getAttribute('data-sort-domain') || '',
                                'ru',
                                { sensitivity: 'base' }
                            );
                        }
                        return cmp * dir;
                    });
                }

                function applySitesFilter(resetPage) {
                    if (resetPage) {
                        sitesPage = 1;
                    }
                    var panel = activePanel();
                    if (!panel) {
                        return;
                    }
                    var q = sitesInput ? sitesInput.value.trim().toLowerCase() : '';
                    var tbody = panel.querySelector('.cabinet-home-sites-tbody');
                    var rows = Array.prototype.slice.call(panel.querySelectorAll('tr[data-cabinet-site-domain]'));
                    var matched = [];
                    rows.forEach(function (row) {
                        var domain = (row.getAttribute('data-cabinet-site-domain') || '').toLowerCase();
                        var matchQ = q === '' || domain.indexOf(q) !== -1;
                        var matchMetrika = !metrikaFilterOff || row.getAttribute('data-metrika-synced') !== '1';
                        var match = matchQ && matchMetrika;
                        row.setAttribute('data-sites-match', match ? '1' : '0');
                        row.classList.add('is-hidden');
                        if (match) {
                            matched.push(row);
                        }
                    });

                    matched = sortSitesRows(matched);
                    if (tbody) {
                        matched.forEach(function (row) {
                            tbody.appendChild(row);
                        });
                    }
                    syncSitesSortHeaders();

                    var totalMatched = matched.length;
                    var totalPages = Math.max(1, Math.ceil(totalMatched / pageSize) || 1);
                    if (sitesPage > totalPages) {
                        sitesPage = totalPages;
                    }
                    if (sitesPage < 1) {
                        sitesPage = 1;
                    }
                    var start = (sitesPage - 1) * pageSize;
                    var end = start + pageSize;
                    matched.forEach(function (row, index) {
                        if (index >= start && index < end) {
                            row.classList.remove('is-hidden');
                        }
                    });

                    var filtered = q !== '' || metrikaFilterOff;
                    var empty = document.getElementById('cabinet-home-sites-filter-empty');
                    var wrap = panel.querySelector('.cabinet-home-sites-table-wrap');
                    var panelEmpty = panel.querySelector('.cabinet-home-sites-empty');
                    if (empty) {
                        empty.classList.toggle('d-none', totalMatched > 0 || !filtered || rows.length === 0);
                    }
                    if (wrap) {
                        wrap.classList.toggle('d-none', totalMatched === 0 && filtered);
                    }
                    if (panelEmpty && !filtered) {
                        panelEmpty.classList.remove('d-none');
                    }

                    if (pagerEl) {
                        var showPager = totalMatched > pageSize;
                        pagerEl.classList.toggle('d-none', !showPager && totalMatched === 0);
                        if (totalMatched === 0) {
                            pagerEl.classList.add('d-none');
                        } else {
                            pagerEl.classList.remove('d-none');
                            var from = totalMatched ? start + 1 : 0;
                            var to = Math.min(end, totalMatched);
                            if (pagerInfoEl) {
                                pagerInfoEl.textContent = formatPagerText(pagerInfoTpl, {
                                    from: from,
                                    to: to,
                                    total: totalMatched,
                                });
                            }
                            if (pagerLabelEl) {
                                pagerLabelEl.textContent = formatPagerText(pagerLabelTpl, {
                                    page: sitesPage,
                                    pages: totalPages,
                                });
                            }
                            if (pagerPrevBtn) {
                                pagerPrevBtn.disabled = sitesPage <= 1;
                            }
                            if (pagerNextBtn) {
                                pagerNextBtn.disabled = sitesPage >= totalPages;
                            }
                            if (!showPager) {
                                // Одна страница — оставляем только текст «1–N из N»
                                if (pagerPrevBtn) pagerPrevBtn.classList.add('d-none');
                                if (pagerNextBtn) pagerNextBtn.classList.add('d-none');
                                if (pagerLabelEl) pagerLabelEl.classList.add('d-none');
                            } else {
                                if (pagerPrevBtn) pagerPrevBtn.classList.remove('d-none');
                                if (pagerNextBtn) pagerNextBtn.classList.remove('d-none');
                                if (pagerLabelEl) pagerLabelEl.classList.remove('d-none');
                            }
                        }
                    }

                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                }

                function setSitesMode(mode) {
                    if (mode !== 'archive' && mode !== 'hidden') {
                        mode = 'active';
                    }
                    sitesMode = mode;
                    sitesSection.querySelectorAll('[data-sites-panel]').forEach(function (panel) {
                        panel.classList.toggle('d-none', panel.getAttribute('data-sites-panel') !== sitesMode);
                    });
                    modeButtons.forEach(function (btn) {
                        btn.classList.toggle('active', btn.getAttribute('data-sites-mode') === sitesMode);
                    });
                    applySitesFilter(true);
                }

                modeButtons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setSitesMode(btn.getAttribute('data-sites-mode'));
                    });
                });

                if (metrikaFilterBtn) {
                    metrikaFilterBtn.addEventListener('click', function () {
                        metrikaFilterOff = !metrikaFilterOff;
                        metrikaFilterBtn.classList.toggle('is-active', metrikaFilterOff);
                        metrikaFilterBtn.setAttribute('aria-pressed', metrikaFilterOff ? 'true' : 'false');
                        applySitesFilter(true);
                    });
                }

                if (pagerPrevBtn) {
                    pagerPrevBtn.addEventListener('click', function () {
                        if (sitesPage <= 1) return;
                        sitesPage -= 1;
                        applySitesFilter(false);
                    });
                }
                if (pagerNextBtn) {
                    pagerNextBtn.addEventListener('click', function () {
                        sitesPage += 1;
                        applySitesFilter(false);
                    });
                }

                document.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-sites-sort]');
                    if (!btn) {
                        return;
                    }
                    if (!btn.closest('#cabinet-home-sites') && !btn.closest('.cabinet-home-sites-float-head')) {
                        return;
                    }
                    e.preventDefault();
                    var key = btn.getAttribute('data-sites-sort');
                    if (!key) {
                        return;
                    }
                    if (sitesSortKey === key) {
                        sitesSortDir = sitesSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sitesSortKey = key;
                        sitesSortDir = key === 'domain' ? 'asc' : 'desc';
                    }
                    applySitesFilter(true);
                });

                function postSiteAction(url, domain, button) {
                    if (!url || !domain) {
                        return;
                    }
                    if (button) {
                        button.disabled = true;
                    }
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ domain: domain }),
                        credentials: 'same-origin',
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                return { ok: res.ok && data && data.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            if (!result.ok) {
                                throw new Error((result.data && result.data.message) || 'error');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            if (button) {
                                button.disabled = false;
                            }
                            alert(@json(__('Could not update site archive')));
                        });
                }

                var pendingAction = null;
                var confirmModalEl = document.getElementById('cabinet-sites-confirm-modal');
                var confirmTextEl = confirmModalEl ? confirmModalEl.querySelector('[data-sites-confirm-text]') : null;
                var confirmOkBtn = confirmModalEl ? confirmModalEl.querySelector('[data-sites-confirm-ok]') : null;
                var confirmTitleEl = document.getElementById('cabinet-sites-confirm-title');

                function showConfirmModal(opts) {
                    pendingAction = opts || null;
                    if (!confirmModalEl || !pendingAction) {
                        return;
                    }
                    if (confirmTitleEl) {
                        confirmTitleEl.textContent = pendingAction.title || @json(__('Confirm'));
                    }
                    if (confirmTextEl) {
                        confirmTextEl.textContent = pendingAction.text || '';
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(confirmModalEl).modal('show');
                    }
                }

                function hideConfirmModal() {
                    if (!confirmModalEl) {
                        return;
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        var inst = bootstrap.Modal.getInstance(confirmModalEl);
                        if (inst) {
                            inst.hide();
                        }
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(confirmModalEl).modal('hide');
                    }
                }

                if (confirmOkBtn) {
                    confirmOkBtn.addEventListener('click', function () {
                        if (!pendingAction) {
                            return;
                        }
                        var action = pendingAction;
                        pendingAction = null;
                        hideConfirmModal();
                        postSiteAction(action.url, action.domain, action.button);
                    });
                }

                function initSitesActionTooltips() {
                    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                        return;
                    }
                    sitesSection.querySelectorAll(
                        '[data-cabinet-site-hide], [data-cabinet-site-archive], [data-cabinet-site-restore]'
                    ).forEach(function (el) {
                        var existing = bootstrap.Tooltip.getInstance(el);
                        if (existing) {
                            existing.dispose();
                        }
                        new bootstrap.Tooltip(el, {
                            container: 'body',
                            trigger: 'hover focus',
                            placement: el.getAttribute('data-bs-placement') || 'top',
                        });
                    });
                }

                sitesSection.addEventListener('click', function (event) {
                    var archiveBtn = event.target.closest('[data-cabinet-site-archive]');
                    var hideBtn = event.target.closest('[data-cabinet-site-hide]');
                    var restoreBtn = event.target.closest('[data-cabinet-site-restore]');
                    var btn = archiveBtn || hideBtn || restoreBtn;
                    if (!btn) {
                        return;
                    }
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        var tip = bootstrap.Tooltip.getInstance(btn);
                        if (tip) {
                            tip.hide();
                        }
                    }
                    var row = btn.closest('tr[data-cabinet-site-domain]');
                    if (!row) {
                        return;
                    }
                    var domain = row.getAttribute('data-cabinet-site-domain') || '';
                    if (restoreBtn) {
                        postSiteAction(sitesSection.getAttribute('data-restore-url'), domain, btn);
                        return;
                    }
                    if (archiveBtn) {
                        showConfirmModal({
                            title: @json(__('Move to archive')),
                            text: @json(__('Confirm archive site')) + ' «' + domain + '»?',
                            url: sitesSection.getAttribute('data-archive-url'),
                            domain: domain,
                            button: btn,
                        });
                        return;
                    }
                    if (hideBtn) {
                        showConfirmModal({
                            title: @json(__('Hide site')),
                            text: @json(__('Confirm hide site')) + ' «' + domain + '»?',
                            url: sitesSection.getAttribute('data-hide-url'),
                            domain: domain,
                            button: btn,
                        });
                    }
                });

                if (sitesInput) {
                    sitesInput.addEventListener('input', function () {
                        applySitesFilter(true);
                    });
                }

                // Столбцы: как в мониторинге позиций — чекбоксы + сохранение на пользователя
                (function initSitesColumns() {
                    var columnsUrl = sitesSection.getAttribute('data-columns-url') || '';
                    var columnsMenu = document.getElementById('cabinet-home-sites-columns-menu');
                    var columnPrefs = {};
                    try {
                        columnPrefs = JSON.parse(sitesSection.getAttribute('data-columns') || '{}') || {};
                    } catch (e) {
                        columnPrefs = {};
                    }
                    var saveTimer = null;

                    function applyColumnVisibility(key, visible) {
                        sitesSection.querySelectorAll(
                            '.cabinet-home-sites-table [data-col="' + key + '"]'
                        ).forEach(function (el) {
                            el.classList.toggle('is-col-hidden', !visible);
                        });
                        document.querySelectorAll(
                            '.cabinet-home-sites-float-head [data-col="' + key + '"]'
                        ).forEach(function (el) {
                            el.classList.toggle('is-col-hidden', !visible);
                        });
                        if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                            window.__cabinetHomeSitesFloatUpdate();
                        }
                    }

                    function scheduleSaveColumns() {
                        if (!columnsUrl) {
                            return;
                        }
                        if (saveTimer) {
                            clearTimeout(saveTimer);
                        }
                        saveTimer = setTimeout(function () {
                            fetch(columnsUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({ columns: columnPrefs }),
                                credentials: 'same-origin',
                            }).catch(function () { /* UI already updated */ });
                        }, 350);
                    }

                    if (!columnsMenu) {
                        return;
                    }

                    columnsMenu.addEventListener('change', function (event) {
                        var input = event.target.closest('.cabinet-home-sites-col-toggle');
                        if (!input) {
                            return;
                        }
                        var key = input.getAttribute('data-col');
                        if (!key) {
                            return;
                        }
                        var visible = !!input.checked;
                        columnPrefs[key] = visible;
                        applyColumnVisibility(key, visible);
                        scheduleSaveColumns();
                    });
                })();

                initSitesActionTooltips();
                applySitesFilter(true);
            }

            // Плавающая шапка таблицы «Ваши сайты» при скролле страницы
            (function initSitesFloatingHeader() {
                var headerNav = document.getElementById('header-nav-bar');
                var updaters = [];

                function stickyTop() {
                    // Если шапка кабинета ещё в зоне viewport сверху — клеимся под неё.
                    // Если уехала вверх (не fixed) — клеимся к верху окна (0).
                    if (!headerNav) {
                        return 0;
                    }
                    var nav = headerNav.getBoundingClientRect();
                    if (nav.bottom > 0) {
                        return Math.ceil(nav.bottom);
                    }
                    return 0;
                }

                function setupWrap(wrap) {
                    var table = wrap.querySelector('table.cabinet-home-sites-table');
                    var thead = table && table.tHead;
                    if (!table || !thead) {
                        return null;
                    }

                    var float = document.createElement('div');
                    float.className = 'cabinet-home-sites-float-head';
                    float.setAttribute('aria-hidden', 'true');
                    var floatTable = document.createElement('table');
                    floatTable.className = table.className.replace('table-hover', '').trim();
                    floatTable.style.tableLayout = 'fixed';
                    floatTable.appendChild(thead.cloneNode(true));
                    float.appendChild(floatTable);
                    document.body.appendChild(float);

                    function syncWidths() {
                        var srcTh = thead.querySelectorAll('th');
                        var dstTh = floatTable.querySelectorAll('th');
                        var tableWidth = Math.ceil(table.getBoundingClientRect().width);
                        floatTable.style.width = tableWidth + 'px';
                        floatTable.style.minWidth = tableWidth + 'px';
                        for (var i = 0; i < srcTh.length; i++) {
                            if (!dstTh[i]) {
                                continue;
                            }
                            dstTh[i].classList.toggle('is-col-hidden', srcTh[i].classList.contains('is-col-hidden'));
                            if (srcTh[i].classList.contains('is-col-hidden')) {
                                dstTh[i].style.width = '';
                                dstTh[i].style.minWidth = '';
                                dstTh[i].style.maxWidth = '';
                                continue;
                            }
                            var w = Math.max(1, Math.round(srcTh[i].getBoundingClientRect().width));
                            dstTh[i].style.width = w + 'px';
                            dstTh[i].style.minWidth = w + 'px';
                            dstTh[i].style.maxWidth = w + 'px';
                            dstTh[i].style.boxSizing = 'border-box';
                            dstTh[i].style.paddingLeft = getComputedStyle(srcTh[i]).paddingLeft;
                            dstTh[i].style.paddingRight = getComputedStyle(srcTh[i]).paddingRight;
                        }
                    }

                    function update() {
                        var panel = wrap.closest('[data-sites-panel]');
                        if ((panel && panel.classList.contains('d-none')) || wrap.offsetParent === null) {
                            float.classList.remove('is-visible');
                            return;
                        }
                        if (document.getElementById('cabinet-home-sites') &&
                            document.getElementById('cabinet-home-sites').classList.contains('is-collapsed')) {
                            float.classList.remove('is-visible');
                            return;
                        }
                        // Не перекрывать модалки (Метрика, подтверждение и т.п.)
                        if (document.body.classList.contains('modal-open') ||
                            document.querySelector('.modal.show, .modal.in, .modal-backdrop')) {
                            float.classList.remove('is-visible');
                            return;
                        }

                        var top = stickyTop();
                        var headRect = thead.getBoundingClientRect();
                        var wrapRect = wrap.getBoundingClientRect();
                        // Показать, когда оригинальная шапка ушла выше линии закрепления,
                        // но таблица ещё на экране.
                        var shouldShow = headRect.bottom <= top + 1 && wrapRect.bottom > top + 40;
                        if (!shouldShow) {
                            float.classList.remove('is-visible');
                            return;
                        }

                        syncWidths();
                        float.style.top = top + 'px';
                        float.style.left = Math.round(wrapRect.left) + 'px';
                        float.style.width = Math.round(wrapRect.width) + 'px';
                        float.scrollLeft = wrap.scrollLeft || 0;
                        float.classList.add('is-visible');
                    }

                    wrap.addEventListener('scroll', update, { passive: true });
                    updaters.push(update);
                    return update;
                }

                document.querySelectorAll('.cabinet-home-sites-table-wrap').forEach(function (wrap) {
                    setupWrap(wrap);
                });

                function tick() {
                    for (var i = 0; i < updaters.length; i++) {
                        updaters[i]();
                    }
                }

                window.__cabinetHomeSitesFloatUpdate = tick;
                // scroll часто на window; на всякий случай — capture + app-main
                window.addEventListener('scroll', tick, { passive: true, capture: true });
                document.addEventListener('scroll', tick, { passive: true, capture: true });
                var appMain = document.querySelector('.app-main');
                if (appMain) {
                    appMain.addEventListener('scroll', tick, { passive: true });
                }
                window.addEventListener('resize', tick);
                document.addEventListener('show.bs.modal', tick);
                document.addEventListener('shown.bs.modal', tick);
                document.addEventListener('hide.bs.modal', tick);
                document.addEventListener('hidden.bs.modal', tick);
                if (typeof $ !== 'undefined' && $.fn.on) {
                    $(document).on('show.bs.modal shown.bs.modal hide.bs.modal hidden.bs.modal', tick);
                }
                setTimeout(tick, 50);
                setTimeout(tick, 300);
            })();

            document.querySelectorAll('.cabinet-home-cards-v2-open').forEach(function (link) {
                link.addEventListener('click', function () {
                    var card = link.closest('[data-project-id]');
                    if (!card || typeof $ === 'undefined') {
                        return;
                    }
                    $.ajax({
                        type: 'post',
                        url: @json(route('click.tracking')),
                        data: {
                            _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            button_text: link.getAttribute('data-track') || 'open_module_cards_v2',
                            url: location.href,
                            project_id: card.getAttribute('data-project-id'),
                        },
                    });
                });
            });

            // Яндекс.Метрика: клик по кружку → OAuth / выбор счётчика
            (function initMetrikaPicker() {
                var root = document.getElementById('cabinet-home-sites');
                var modalEl = document.getElementById('cabinet-metrika-modal');
                if (!root || !modalEl) {
                    return;
                }
                var csrfEl = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = csrfEl ? csrfEl.getAttribute('content') : '';
                var currentDomain = '';
                var allCounters = [];
                var selectedCounterId = 0;
                var listEl = modalEl.querySelector('[data-metrika-list]');
                var loadingEl = modalEl.querySelector('[data-metrika-loading]');
                var errorEl = modalEl.querySelector('[data-metrika-error]');
                var authEl = modalEl.querySelector('[data-metrika-auth]');
                var authLink = modalEl.querySelector('[data-metrika-auth-link]');
                var domainLabel = modalEl.querySelector('[data-metrika-domain-label]');
                var currentEl = modalEl.querySelector('[data-metrika-current]');
                var unbindBtn = modalEl.querySelector('[data-metrika-unbind]');
                var searchWrap = modalEl.querySelector('[data-metrika-search-wrap]');
                var searchInput = modalEl.querySelector('[data-metrika-search]');

                function showModal() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        return;
                    }
                    if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modalEl).modal('show');
                    }
                }

                function connectUrl(domain) {
                    var base = root.getAttribute('data-metrika-connect-url') || '';
                    var ret = root.getAttribute('data-metrika-return') || location.href;
                    return base + (base.indexOf('?') === -1 ? '?' : '&') +
                        'domain=' + encodeURIComponent(domain || '') +
                        '&return=' + encodeURIComponent(ret);
                }

                function setError(msg) {
                    if (!errorEl) return;
                    errorEl.textContent = msg || '';
                    errorEl.classList.toggle('d-none', !msg);
                }

                function setLoading(on) {
                    if (loadingEl) loadingEl.classList.toggle('d-none', !on);
                }

                function setSearchVisible(on) {
                    if (searchWrap) searchWrap.classList.toggle('d-none', !on);
                    if (!on && searchInput) searchInput.value = '';
                }

                function filterCounters(counters, query) {
                    var q = String(query || '').trim().toLowerCase();
                    if (!q) return counters.slice();
                    return counters.filter(function (c) {
                        var name = String(c.name || '').toLowerCase();
                        var site = String(c.site || '').toLowerCase();
                        var id = String(c.id || '');
                        return name.indexOf(q) !== -1 || site.indexOf(q) !== -1 || id.indexOf(q) !== -1;
                    });
                }

                function renderCounters(counters, selectedId) {
                    if (!listEl) return;
                    listEl.innerHTML = '';
                    if (!counters.length) {
                        listEl.innerHTML = '<div class="list-group-item text-secondary small">' +
                            (allCounters.length
                                ? @json(__('No counters match the search'))
                                : @json(__('No Metrika counters found'))) + '</div>';
                        return;
                    }
                    counters.forEach(function (c) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2';
                        btn.setAttribute('data-metrika-counter-id', String(c.id));
                        if (selectedId && Number(selectedId) === Number(c.id)) {
                            btn.classList.add('active');
                        }
                        btn.innerHTML =
                            '<span class="text-start">' +
                            '<strong>' + String(c.name || ('#' + c.id)).replace(/</g, '&lt;') + '</strong>' +
                            '<br><span class="small opacity-75">' + String(c.site || '').replace(/</g, '&lt;') +
                            ' · ID ' + c.id + '</span></span>' +
                            '<span class="cabinet-metrika-counter-status flex-shrink-0 align-self-center">' +
                            (selectedId && Number(selectedId) === Number(c.id)
                                ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                : '') +
                            '</span>';
                        btn.addEventListener('click', function () {
                            bindCounter(c.id, btn);
                        });
                        listEl.appendChild(btn);
                    });
                }

                function applyCounterFilter() {
                    renderCounters(
                        filterCounters(allCounters, searchInput ? searchInput.value : ''),
                        selectedCounterId
                    );
                }

                function setBindingBusy(busy, activeBtn) {
                    if (searchInput) {
                        searchInput.disabled = !!busy;
                    }
                    if (unbindBtn) {
                        unbindBtn.disabled = !!busy;
                    }
                    if (!listEl) return;
                    listEl.querySelectorAll('button[data-metrika-counter-id]').forEach(function (btn) {
                        var isActive = busy && activeBtn && btn === activeBtn;
                        btn.disabled = !!busy && !isActive;
                        btn.classList.toggle('cabinet-metrika-counter--dimmed', !!busy && !isActive);
                        btn.setAttribute('aria-busy', isActive ? 'true' : 'false');
                        if (!busy) {
                            btn.classList.remove('cabinet-metrika-counter--binding', 'cabinet-metrika-counter--dimmed');
                            btn.removeAttribute('aria-busy');
                            var status = btn.querySelector('.cabinet-metrika-counter-status');
                            if (status && status.getAttribute('data-binding') === '1') {
                                status.removeAttribute('data-binding');
                                status.innerHTML = Number(btn.getAttribute('data-metrika-counter-id')) === Number(selectedCounterId)
                                    ? '<span class="badge text-bg-light text-dark border">' + @json(__('Selected')) + '</span>'
                                    : '';
                            }
                        }
                    });
                    if (busy && activeBtn) {
                        activeBtn.classList.add('cabinet-metrika-counter--binding');
                        activeBtn.classList.remove('active');
                        var statusEl = activeBtn.querySelector('.cabinet-metrika-counter-status');
                        if (statusEl) {
                            statusEl.setAttribute('data-binding', '1');
                            statusEl.innerHTML =
                                '<span class="cabinet-metrika-binding-label">' +
                                '<span class="cabinet-metrika-spinner" aria-hidden="true"></span>' +
                                @json(__('Linking Metrika counter')) +
                                '…</span>';
                        }
                    }
                    if (typeof window.__cabinetHomeSitesFloatUpdate === 'function') {
                        window.__cabinetHomeSitesFloatUpdate();
                    }
                }

                function openForDomain(domain) {
                    currentDomain = domain || '';
                    allCounters = [];
                    selectedCounterId = 0;
                    if (domainLabel) domainLabel.textContent = currentDomain || '—';
                    if (authEl) authEl.classList.add('d-none');
                    if (listEl) listEl.innerHTML = '';
                    setSearchVisible(false);
                    if (currentEl) {
                        currentEl.classList.add('d-none');
                        currentEl.textContent = '';
                    }
                    if (unbindBtn) unbindBtn.classList.add('d-none');
                    setError('');
                    setLoading(true);
                    if (authLink) authLink.href = connectUrl(currentDomain);
                    showModal();

                    var bindingUrl = root.getAttribute('data-metrika-binding-url') +
                        '?domain=' + encodeURIComponent(currentDomain);
                    fetch(bindingUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (info) {
                            if (!info || !info.ok) {
                                throw new Error('binding');
                            }
                            if (!info.configured) {
                                setLoading(false);
                                setError(@json(__('Yandex Metrika is not configured')));
                                return null;
                            }
                            if (!info.connected) {
                                setLoading(false);
                                if (authEl) authEl.classList.remove('d-none');
                                return null;
                            }
                            if (info.binding && currentEl) {
                                currentEl.textContent = @json(__('Current counter')) + ': ' +
                                    (info.binding.counter_name || ('#' + info.binding.counter_id)) +
                                    (info.binding.counter_site ? ' (' + info.binding.counter_site + ')' : '');
                                currentEl.classList.remove('d-none');
                                if (unbindBtn) unbindBtn.classList.remove('d-none');
                            }
                            return fetch(root.getAttribute('data-metrika-counters-url'), {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            }).then(function (r) {
                                return r.json().then(function (data) {
                                    return { status: r.status, data: data, selected: info.binding && info.binding.counter_id };
                                });
                            });
                        })
                        .then(function (result) {
                            setLoading(false);
                            if (!result) return;
                            if (result.status === 401 || (result.data && result.data.need_auth)) {
                                if (authEl) authEl.classList.remove('d-none');
                                return;
                            }
                            if (!result.data || !result.data.ok) {
                                setError((result.data && result.data.message) || @json(__('Could not load Metrika counters')));
                                return;
                            }
                            allCounters = result.data.counters || [];
                            selectedCounterId = result.selected || 0;
                            setSearchVisible(allCounters.length > 0);
                            applyCounterFilter();
                        })
                        .catch(function () {
                            setLoading(false);
                            setError(@json(__('Could not load Metrika counters')));
                        });
                }

                function bindCounter(counterId, btn) {
                    setError('');
                    setBindingBusy(true, btn || null);
                    fetch(root.getAttribute('data-metrika-bind-url'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ domain: currentDomain, counter_id: counterId }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) {
                                throw new Error((data && data.message) || 'bind');
                            }
                            window.location.reload();
                        })
                        .catch(function () {
                            setBindingBusy(false);
                            setError(@json(__('Could not bind Metrika counter')));
                        });
                }

                if (unbindBtn) {
                    unbindBtn.addEventListener('click', function () {
                        if (!currentDomain) return;
                        setLoading(true);
                        fetch(root.getAttribute('data-metrika-unbind-url'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ domain: currentDomain }),
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || !data.ok) throw new Error('unbind');
                                window.location.reload();
                            })
                            .catch(function () {
                                setLoading(false);
                                setError(@json(__('Could not unbind Metrika counter')));
                            });
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', applyCounterFilter);
                }

                root.addEventListener('click', function (event) {
                    var btn = event.target.closest('[data-cabinet-metrika-dot]');
                    if (!btn) return;
                    event.preventDefault();
                    openForDomain(btn.getAttribute('data-domain') || '');
                });

                try {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('metrika_picker') === '1') {
                        var d = params.get('metrika_domain') || '';
                        openForDomain(d);
                        if (window.history && window.history.replaceState) {
                            params.delete('metrika_picker');
                            params.delete('metrika_domain');
                            var q = params.toString();
                            window.history.replaceState({}, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
                        }
                    }
                } catch (e) {}
            })();
        })();
    </script>
@endsection
