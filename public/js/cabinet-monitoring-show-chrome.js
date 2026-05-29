/**
 * /monitoring/{id} — SER: «Обзор» = графики, «Ключевые слова» = таблица (без дубля «Позиции»).
 */
(function () {
    'use strict';

    var root = document.getElementById('cabinet-mon-project-root');
    if (!root) {
        return;
    }

    var cfg = window.cabinetMonProjectConfig || {};
    var storageKey = 'cabinet-mon-project-view-v2';

    function relayoutKeywordsTable() {
        if (!window.jQuery || !jQuery.fn.DataTable) {
            return;
        }
        var $table = jQuery('#monitoringTable');
        if (!$table.length || !jQuery.fn.DataTable.isDataTable($table)) {
            return;
        }
        var api = $table.DataTable();
        var run = function () {
            api.columns.adjust();
            var $wrapper = $table.closest('.dataTables_wrapper');
            var $body = $wrapper.find('.dataTables_scrollBody');
            var $head = $wrapper.find('.dataTables_scrollHead');
            if ($body.length && $head.length) {
                $head.width($body.outerWidth());
            }
        };
        requestAnimationFrame(function () {
            requestAnimationFrame(run);
        });
        setTimeout(run, 100);
        setTimeout(run, 320);
    }

    var relayoutTimer;
    function scheduleRelayoutKeywordsTable() {
        clearTimeout(relayoutTimer);
        relayoutTimer = setTimeout(relayoutKeywordsTable, 50);
    }

    function setView(mode) {
        if (mode !== 'overview' && mode !== 'keywords') {
            mode = 'keywords';
        }
        root.setAttribute('data-view', mode);
        try {
            localStorage.setItem(storageKey, mode);
            if (mode === 'overview') {
                window.location.hash = 'overview';
            } else {
                window.location.hash = 'keywords';
            }
        } catch (e) {}

        root.querySelectorAll('[data-mon-view-tab]').forEach(function (btn) {
            var active = btn.getAttribute('data-mon-view-tab') === mode;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-mon-view-panel]').forEach(function (panel) {
            var panelMode = panel.getAttribute('data-mon-view-panel');
            panel.classList.toggle('d-none', panelMode !== mode);
        });

        root.querySelectorAll('[data-mon-view-hint]').forEach(function (hint) {
            hint.classList.toggle('d-none', hint.getAttribute('data-mon-view-hint') !== mode);
        });

        if (mode === 'keywords') {
            scheduleRelayoutKeywordsTable();
        }
    }

    root.querySelectorAll('[data-mon-view-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setView(btn.getAttribute('data-mon-view-tab'));
        });
    });

    var initial = 'keywords';
    if (window.location.hash === '#overview') {
        initial = 'overview';
    } else if (window.location.hash === '#keywords' || window.location.hash === '#detailed') {
        initial = 'keywords';
    } else {
        try {
            var saved = localStorage.getItem(storageKey);
            if (saved === 'overview') {
                initial = 'overview';
            } else if (saved === 'detailed') {
                initial = 'keywords';
            }
        } catch (e2) {}
    }
    setView(initial);

    function formatDelta(val) {
        if (val === null || val === undefined || val === '') {
            return '';
        }
        var n = Number(val);
        if (isNaN(n) || n === 0) {
            return '';
        }
        return (n > 0 ? '+' : '') + n;
    }

    function deltaClass(val) {
        var n = Number(val);
        if (isNaN(n) || n === 0) {
            return '';
        }
        return n > 0 ? 'is-up' : 'is-down';
    }

    function setKpiLoading(loading) {
        var kpis = document.getElementById('cabinetMonProjectKpis');
        if (!kpis) {
            return;
        }
        if (loading) {
            kpis.classList.add('is-loading');
            kpis.setAttribute('aria-busy', 'true');
        } else {
            kpis.classList.remove('is-loading');
            kpis.removeAttribute('aria-busy');
        }
    }

    function setKpiLoadError() {
        var kpis = document.getElementById('cabinetMonProjectKpis');
        var loader = document.getElementById('cabinetMonProjectKpisLoader');
        if (!kpis || !loader) {
            return;
        }
        kpis.classList.remove('is-loading');
        kpis.removeAttribute('aria-busy');
        loader.classList.add('is-error');
        var spin = loader.querySelector('.cabinet-mon-loader__icon');
        if (spin) {
            spin.remove();
        }
        var label = loader.querySelector('.cabinet-mon-loader__label');
        if (label) {
            label.textContent = cfg.i18n && cfg.i18n.kpiLoadError ? cfg.i18n.kpiLoadError : 'Ошибка загрузки';
        }
    }

    function hideKpiLoader() {
        setKpiLoading(false);
        var loader = document.getElementById('cabinetMonProjectKpisLoader');
        if (loader) {
            loader.remove();
        }
    }

    function fillKpi(summary) {
        if (!summary) {
            return;
        }
        var map = {
            top1: summary.top1,
            top3: summary.top3,
            top10: summary.top10,
            top30: summary.top30,
            top100: summary.top100,
            middle: summary.middle,
            words: summary.words,
            snapshot_at: summary.snapshot_at,
        };
        Object.keys(map).forEach(function (key) {
            var el = root.querySelector('[data-kpi="' + key + '"]');
            if (el) {
                el.textContent = map[key] !== null && map[key] !== undefined && map[key] !== '' ? map[key] : '—';
            }
        });
        [
            ['top1', summary.diff_top1],
            ['top3', summary.diff_top3],
            ['top10', summary.diff_top10],
            ['top30', summary.diff_top30],
            ['top100', summary.diff_top100],
        ].forEach(function (pair) {
            var el = root.querySelector('[data-kpi-delta="' + pair[0] + '"]');
            if (!el) {
                return;
            }
            el.textContent = formatDelta(pair[1]);
            el.className = 'cabinet-mon-project-kpi__delta ' + deltaClass(pair[1]);
        });

        var hintEl = root.querySelector('[data-kpi-hint="snapshot"]');
        if (hintEl) {
            if (summary.snapshot_scope === 'region') {
                hintEl.textContent = cfg.i18n && cfg.i18n.kpiSnapshotRegion ? cfg.i18n.kpiSnapshotRegion : '';
            } else {
                hintEl.textContent = cfg.i18n && cfg.i18n.kpiSnapshotProject ? cfg.i18n.kpiSnapshotProject : '';
            }
        }
    }

    function loadKpi() {
        if (!cfg.statsUrl || !cfg.projectId) {
            hideKpiLoader();
            return;
        }
        setKpiLoading(true);
        var url =
            cfg.statsUrl +
            (cfg.statsUrl.indexOf('?') >= 0 ? '&' : '?') +
            'projectId=' +
            encodeURIComponent(cfg.projectId);
        var params = new URLSearchParams(window.location.search);
        if (params.get('region')) {
            url += '&regionId=' + encodeURIComponent(params.get('region'));
        }
        fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('kpi stats failed');
                }
                return r.json();
            })
            .then(function (data) {
                hideKpiLoader();
                if (data && data.summary) {
                    fillKpi(data.summary);
                }
            })
            .catch(function () {
                setKpiLoadError();
            });
    }

    loadKpi();

    window.addEventListener('resize', function () {
        if (root.getAttribute('data-view') === 'keywords') {
            scheduleRelayoutKeywordsTable();
        }
    });

    window.cabinetMonitoringShowChrome = {
        relayoutKeywordsTable: relayoutKeywordsTable,
        onTableReady: function (api) {
            window.__cabinetMonKeywordsTableApi = api;
            if (root.getAttribute('data-view') === 'keywords') {
                scheduleRelayoutKeywordsTable();
            }
        },
    };
})();
