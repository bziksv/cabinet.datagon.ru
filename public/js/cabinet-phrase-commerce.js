(function () {
    'use strict';

    var root = document.getElementById('cabinetPcPage');
    if (!root) {
        return;
    }

    var analyzeUrl = root.getAttribute('data-analyze-url');
    var exportUrl = root.getAttribute('data-export-url');
    var historyBase = root.getAttribute('data-history-url');
    var historyStoreUrl = root.getAttribute('data-history-store-url');
    var regionsUrl = root.getAttribute('data-regions-url');
    var csrf = root.getAttribute('data-csrf');
    var canSave = root.getAttribute('data-can-save') === '1';
    var costYandex = parseInt(root.getAttribute('data-cost-yandex') || '2', 10) || 2;
    var costGoogle = parseInt(root.getAttribute('data-cost-google') || '4', 10) || 4;

    var form = document.getElementById('cabinetPcForm');
    var submitBtn = document.getElementById('cabinetPcSubmit');
    var clearBtn = document.getElementById('cabinetPcClear');
    var statusEl = document.getElementById('cabinetPcStatus');
    var phrasesEl = document.getElementById('cabinetPcPhrases');
    var engineYandex = document.getElementById('pc_engine_yandex');
    var engineGoogle = document.getElementById('pc_engine_google');
    var yandexWrap = document.getElementById('cabinetPcYandexWrap');
    var googleWrap = document.getElementById('cabinetPcGoogleWrap');
    var resultsWrap = document.getElementById('cabinetPcResultsWrap');
    var resultsBody = document.querySelector('#cabinetPcResults tbody');
    var resultsMeta = document.getElementById('cabinetPcResultsMeta');
    var summaryEl = document.getElementById('cabinetPcSummary');
    var exportBtn = document.getElementById('cabinetPcExport');
    var costPreview = document.getElementById('cabinetPcCostPreview');
    var costText = document.getElementById('cabinetPcCostText');
    var progressEl = document.getElementById('cabinetPcProgress');
    var progressTitle = document.getElementById('cabinetPcProgressTitle');
    var progressSub = document.getElementById('cabinetPcProgressSub');
    var progressTime = document.getElementById('cabinetPcProgressTime');
    var progressBar = document.getElementById('cabinetPcProgressBar');

    var lastRows = [];
    var lastCost = 0;
    var groupedRows = [];
    var filteredGroups = [];
    var progressTimer = null;
    var progressStartedAt = 0;
    var filters = {
        yandex: true,
        google: true,
        gz: false,
        gnz: false,
        com: false,
        nocom: false,
    };

    function setStatus(text, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = text || '';
        statusEl.classList.toggle('text-danger', !!isError);
        statusEl.classList.toggle('text-muted', !isError);
    }

    function listPhrases() {
        var raw = (phrasesEl && phrasesEl.value) || '';
        var seen = {};
        var out = [];
        raw.split(/\r\n|\r|\n/).forEach(function (line) {
            var p = String(line || '').replace(/\s+/g, ' ').trim();
            if (!p) {
                return;
            }
            var key = p.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            out.push(p);
        });
        return out;
    }

    function countPhrases() {
        return listPhrases().length;
    }

    function formatElapsed(ms) {
        var sec = Math.max(0, Math.floor(ms / 1000));
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function setProgressVisible(on) {
        if (!progressEl) {
            return;
        }
        progressEl.classList.toggle('d-none', !on);
        if (!on && progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
    }

    function updateProgress(done, total, phrase) {
        var pct = total > 0 ? Math.min(99, Math.round((done / total) * 100)) : 0;
        if (progressBar) {
            progressBar.style.width = pct + '%';
            progressBar.setAttribute('aria-valuenow', String(pct));
        }
        if (progressTitle) {
            progressTitle.textContent =
                'Сбор выдачи · фраза ' + Math.min(done + 1, total) + ' из ' + total;
        }
        if (progressSub) {
            progressSub.textContent = phrase
                ? '«' + phrase + '» — ТОП-20 × 2 региона, подождите'
                : 'Собираем выдачу…';
        }
        if (progressTime) {
            progressTime.textContent = formatElapsed(Date.now() - progressStartedAt);
        }
    }

    function finishProgress() {
        if (progressBar) {
            progressBar.style.width = '100%';
            progressBar.setAttribute('aria-valuenow', '100');
        }
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
        setTimeout(function () {
            setProgressVisible(false);
        }, 350);
    }

    function buildSummaryClient(rows) {
        var geoDep = 0;
        var geoInd = 0;
        var comm = 0;
        var info = 0;
        var n = rows.length;
        var locSum = 0;
        var comSum = 0;
        rows.forEach(function (r) {
            var g = (r.geo && r.geo.code) || '';
            if (g === 'geo_dependent') {
                geoDep += 1;
            } else if (g === 'geo_independent') {
                geoInd += 1;
            }
            var c = (r.commerce && r.commerce.code) || '';
            if (c === 'commercial') {
                comm += 1;
            } else if (c === 'informational') {
                info += 1;
            }
            locSum += (r.localization && r.localization.pct) || 0;
            comSum += (r.commerce && r.commerce.pct) || 0;
        });
        return {
            total: n,
            geo_dependent: geoDep,
            geo_independent: geoInd,
            commercial: comm,
            informational: info,
            avg_localization: n > 0 ? Math.round((locSum / n) * 10) / 10 : 0,
            avg_commerce: n > 0 ? Math.round((comSum / n) * 10) / 10 : 0,
        };
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, data: data };
            });
        });
    }

    function enginesCount() {
        var n = 0;
        if (engineYandex && engineYandex.checked) {
            n += 1;
        }
        if (engineGoogle && engineGoogle.checked) {
            n += 1;
        }
        return n;
    }

    function estimateCostClient() {
        var n = countPhrases();
        var cost = 0;
        if (engineYandex && engineYandex.checked) {
            cost += n * costYandex;
        }
        if (engineGoogle && engineGoogle.checked) {
            cost += n * costGoogle;
        }
        return cost;
    }

    function pluralLimit(n) {
        var abs = Math.abs(n) % 100;
        var last = abs % 10;
        var one = (costPreview && costPreview.getAttribute('data-unit-one')) || 'лимит';
        var few = (costPreview && costPreview.getAttribute('data-unit-few')) || 'лимита';
        var many = (costPreview && costPreview.getAttribute('data-unit-many')) || 'лимитов';
        if (abs > 10 && abs < 20) {
            return many;
        }
        if (last === 1) {
            return one;
        }
        if (last >= 2 && last <= 4) {
            return few;
        }
        return many;
    }

    function updateCostPreview() {
        var cost = estimateCostClient();
        var label = (costPreview && costPreview.getAttribute('data-label')) || 'Спишется';
        if (costText) {
            costText.innerHTML =
                label + ' <strong id="cabinetPcCostValue">' + String(cost) + '</strong> ' + pluralLimit(cost);
        }
    }

    function syncEngineRegions() {
        if (yandexWrap) {
            yandexWrap.classList.toggle('d-none', !(engineYandex && engineYandex.checked));
        }
        if (googleWrap) {
            googleWrap.classList.toggle('d-none', !(engineGoogle && engineGoogle.checked));
        }
        updateCostPreview();
    }

    function initRegionSelect(el) {
        if (!el || !window.jQuery || !jQuery.fn.select2) {
            return;
        }
        var engine = el.getAttribute('data-engine') || 'yandex';
        jQuery(el).select2({
            width: '100%',
            theme: 'bootstrap4',
            placeholder: 'Регион',
            allowClear: false,
            ajax: {
                url: regionsUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { engine: engine, q: params.term || '', limit: 30 };
                },
                processResults: function (data) {
                    var items = (data && data.results) || [];
                    return {
                        results: items.map(function (r) {
                            return { id: String(r.id), text: r.text || r.name || String(r.id) };
                        }),
                    };
                },
            },
        });
    }

    function badge(code, label, extra) {
        var span = document.createElement('span');
        span.className = 'cabinet-pc-badge cabinet-pc-badge--' + (code || 'unknown');
        span.textContent = label || code || '';
        if (extra) {
            span.textContent += ' · ' + extra;
        }
        return span;
    }

    function tipText(attr, fallback) {
        return root.getAttribute(attr) || fallback || '';
    }

    function initTips(scope) {
        var node = scope || root;
        if (!node || !node.querySelectorAll) {
            return;
        }
        node.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            try {
                if (window.bootstrap && bootstrap.Tooltip) {
                    var prev = bootstrap.Tooltip.getInstance
                        ? bootstrap.Tooltip.getInstance(el)
                        : null;
                    if (prev) {
                        prev.dispose();
                    }
                    new bootstrap.Tooltip(el);
                } else if (window.jQuery && jQuery.fn.tooltip) {
                    jQuery(el).tooltip();
                }
            } catch (e) {
                /* ignore */
            }
        });
    }

    function renderSummary(summary) {
        if (!summaryEl) {
            return;
        }
        summaryEl.innerHTML = '';
        var s = summary || {};
        var cards = [
            {
                t: 'Всего строк',
                v: s.total || 0,
                tip: tipText('data-tip-total', 'Число строк результата'),
            },
            {
                t: 'Геозависимые',
                v: s.geo_dependent || 0,
                tip: tipText('data-tip-gz', ''),
            },
            {
                t: 'Геонезависимые',
                v: s.geo_independent || 0,
                tip: tipText('data-tip-gnz', ''),
            },
            {
                t: 'Коммерческие',
                v: s.commercial || 0,
                tip: tipText('data-tip-com', ''),
            },
            {
                t: 'Информационные',
                v: s.informational || 0,
                tip: tipText('data-tip-info', ''),
            },
            {
                t: 'Ср. локализация',
                v: (s.avg_localization || 0) + '%',
                tip: tipText('data-tip-loc', ''),
            },
            {
                t: 'Ср. коммерция',
                v: (s.avg_commerce || 0) + '%',
                tip: tipText('data-tip-com-avg', ''),
            },
        ];
        cards.forEach(function (c) {
            var div = document.createElement('div');
            div.className = 'cabinet-pc-summary__card';
            var title = document.createElement('span');
            title.className = 'small text-secondary cabinet-pc-summary__title';
            title.appendChild(document.createTextNode(c.t + ' '));
            if (c.tip) {
                var tip = document.createElement('i');
                tip.className = 'bi bi-question-circle text-muted cabinet-pc-tip';
                tip.setAttribute('data-bs-toggle', 'tooltip');
                tip.setAttribute('data-bs-placement', 'top');
                tip.setAttribute('title', c.tip);
                tip.setAttribute('aria-hidden', 'true');
                title.appendChild(tip);
            }
            var value = document.createElement('strong');
            value.textContent = String(c.v);
            div.appendChild(title);
            div.appendChild(value);
            summaryEl.appendChild(div);
        });
        initTips(summaryEl);
    }

    function typeLabel(type) {
        var map = {
            aggregators: 'агрегатор',
            ecommerce: 'магазин',
            organizations: 'организация',
            info: 'инфо',
            media: 'медиа',
            unknown: 'н/д',
        };
        return map[type] || type || 'н/д';
    }

    function groupRowsByPhrase(rows) {
        var map = {};
        var order = [];
        (rows || []).forEach(function (row) {
            var key = String(row.phrase || '');
            if (!map[key]) {
                map[key] = { phrase: key, yandex: null, google: null };
                order.push(key);
            }
            if (row.engine === 'google') {
                map[key].google = row;
            } else {
                map[key].yandex = row;
            }
        });
        return order.map(function (k) {
            return map[k];
        });
    }

    function engineMatchesFilters(row) {
        if (!row) {
            return false;
        }
        var geoCode = (row.geo && row.geo.code) || '';
        var comCode = (row.commerce && row.commerce.code) || '';
        var geoOk = true;
        if (filters.gz || filters.gnz) {
            geoOk =
                (filters.gz && geoCode === 'geo_dependent') ||
                (filters.gnz && geoCode === 'geo_independent');
        }
        var comOk = true;
        if (filters.com || filters.nocom) {
            comOk =
                (filters.com && comCode === 'commercial') ||
                (filters.nocom && comCode !== 'commercial');
        }
        return geoOk && comOk;
    }

    function groupMatchesFilters(group) {
        var candidates = [];
        if (filters.yandex && group.yandex) {
            candidates.push(group.yandex);
        }
        if (filters.google && group.google) {
            candidates.push(group.google);
        }
        if (!candidates.length) {
            return false;
        }
        if (!filters.gz && !filters.gnz && !filters.com && !filters.nocom) {
            return true;
        }
        return candidates.some(engineMatchesFilters);
    }

    function syncFilterButtons() {
        var wrap = document.getElementById('cabinetPcFilters');
        if (!wrap) {
            return;
        }
        var allOn =
            filters.yandex &&
            filters.google &&
            !filters.gz &&
            !filters.gnz &&
            !filters.com &&
            !filters.nocom;
        Array.prototype.forEach.call(wrap.querySelectorAll('[data-filter]'), function (btn) {
            var key = btn.getAttribute('data-filter');
            var on = key === 'all' ? allOn : !!filters[key];
            btn.classList.toggle('is-active', on);
        });
    }

    function renderSerpList(items, title) {
        var wrap = document.createElement('div');
        wrap.className = 'cabinet-pc-serp-col';
        var h = document.createElement('div');
        h.className = 'cabinet-pc-serp-col__title';
        h.textContent = title;
        wrap.appendChild(h);
        var ol = document.createElement('ol');
        ol.className = 'cabinet-pc-serp-list';
        (items || []).forEach(function (item) {
            var li = document.createElement('li');
            if (item.shared) {
                li.classList.add('is-shared');
            }
            var domain = document.createElement('a');
            domain.href = item.url || '#';
            domain.target = '_blank';
            domain.rel = 'noopener noreferrer';
            domain.textContent = item.domain || item.url || '—';
            li.appendChild(domain);
            var meta = document.createElement('span');
            meta.className = 'cabinet-pc-serp-meta';
            meta.textContent =
                typeLabel(item.type) + (item.shared ? ' · общий' : '');
            li.appendChild(meta);
            ol.appendChild(li);
        });
        if (!(items && items.length)) {
            var empty = document.createElement('div');
            empty.className = 'small text-secondary';
            empty.textContent = 'Нет данных выдачи';
            wrap.appendChild(empty);
        } else {
            wrap.appendChild(ol);
        }
        return wrap;
    }

    function renderEngineCell(row, engine) {
        var td = document.createElement('td');
        td.className =
            'cabinet-pc-engine ' +
            (engine === 'google' ? 'cabinet-pc-col-google' : 'cabinet-pc-col-yandex');
        if (!row) {
            td.classList.add('is-empty');
            td.textContent = '—';
            return td;
        }
        var geo = row.geo || {};
        var loc = row.localization || {};
        var com = row.commerce || {};

        var overlapLabel =
            geo.overlap_pct != null
                ? 'сходство ' + geo.overlap_pct + '%'
                : '';
        td.appendChild(badge(geo.code, geo.label, overlapLabel));

        var regions = document.createElement('div');
        regions.className = 'cabinet-pc-engine__regions';
        regions.textContent =
            (row.region_name || '') +
            (row.region_contrast_name ? ' ↔ ' + row.region_contrast_name : '');
        td.appendChild(regions);

        var metrics = document.createElement('div');
        metrics.className = 'cabinet-pc-engine__metrics';
        metrics.appendChild(
            badge(loc.code, 'Локализация', loc.pct != null ? loc.pct + '%' : '')
        );
        metrics.appendChild(document.createTextNode(' '));
        metrics.appendChild(
            badge(com.code, com.label || 'Коммерция', com.pct != null ? com.pct + '%' : '')
        );
        td.appendChild(metrics);

        if (geo.shared != null) {
            var shared = document.createElement('div');
            shared.className = 'cabinet-pc-engine__shared';
            var base = geo.base_count != null ? geo.base_count : null;
            var hosts = (geo.shared_hosts || []).slice(0, 3).join(', ');
            shared.textContent =
                'общих хостов: ' +
                String(geo.shared || 0) +
                (base != null ? ' из ' + base : '') +
                (hosts ? ' · ' + hosts + ((geo.shared_hosts || []).length > 3 ? '…' : '') : '');
            td.appendChild(shared);
        }
        if (geo.incomplete || (row.serp_count != null && row.serp_contrast_count != null && row.serp_count !== row.serp_contrast_count)) {
            var warn = document.createElement('div');
            warn.className = 'cabinet-pc-engine__warn';
            warn.textContent =
                'неполная выдача: ' +
                String(row.serp_count || 0) +
                ' vs ' +
                String(row.serp_contrast_count || 0);
            td.appendChild(warn);
        }
        return td;
    }

    function renderSerpDetail(group) {
        var detailTr = document.createElement('tr');
        detailTr.className = 'cabinet-pc-detail d-none';
        var detailTd = document.createElement('td');
        detailTd.colSpan = 4;
        var box = document.createElement('div');
        box.className = 'cabinet-pc-serp';

        [['yandex', 'Яндекс', group.yandex], ['google', 'Google', group.google]].forEach(function (pack) {
            var row = pack[2];
            if (!row) {
                return;
            }
            var block = document.createElement('div');
            block.className = 'cabinet-pc-serp-engine mb-3';
            var title = document.createElement('div');
            title.className = 'cabinet-pc-serp-col__title';
            title.textContent =
                pack[1] +
                ': ' +
                (row.region_name || '') +
                ' ↔ ' +
                (row.region_contrast_name || '') +
                ' · зелёным — общие хосты';
            block.appendChild(title);
            var grid = document.createElement('div');
            grid.className = 'cabinet-pc-serp-grid';
            grid.appendChild(
                renderSerpList(row.serp_primary || [], (row.region_name || 'Основной') + ' · ' + (row.serp_count || 0))
            );
            grid.appendChild(
                renderSerpList(
                    row.serp_contrast || [],
                    (row.region_contrast_name || 'Контрольный') + ' · ' + (row.serp_contrast_count || 0)
                )
            );
            block.appendChild(grid);
            box.appendChild(block);
        });

        detailTd.appendChild(box);
        detailTr.appendChild(detailTd);
        return detailTr;
    }

    function filterScopeHint() {
        var parts = [];
        if (filters.yandex) {
            parts.push('Яндекс');
        }
        if (filters.google) {
            parts.push('Google');
        }
        var metrics = [];
        if (filters.gz) {
            metrics.push('геозависимые');
        }
        if (filters.gnz) {
            metrics.push('геонезависимые');
        }
        if (filters.com) {
            metrics.push('коммерческие');
        }
        if (filters.nocom) {
            metrics.push('некоммерческие');
        }
        if (!metrics.length) {
            return parts.length === 2 ? '' : 'фильтр по ПС: ' + parts.join(' + ') + ' (для гео/коммерции)';
        }
        return 'фильтр: ' + parts.join(' + ') + ' · ' + metrics.join(', ');
    }

    function renderGroupedTable() {
        if (!resultsBody) {
            return;
        }
        resultsBody.innerHTML = '';
        filteredGroups = groupedRows.filter(groupMatchesFilters);

        var countEl = document.getElementById('cabinetPcFilterCount');
        var hint = filterScopeHint();
        if (countEl) {
            countEl.textContent =
                'показано ' +
                filteredGroups.length +
                ' из ' +
                groupedRows.length +
                (hint ? ' · ' + hint : '');
        }
        if (resultsMeta) {
            resultsMeta.textContent =
                ' — ' +
                groupedRows.length +
                ' фраз · списано ' +
                lastCost +
                (filteredGroups.length !== groupedRows.length
                    ? ' · фильтр: ' + filteredGroups.length
                    : '');
        }

        filteredGroups.forEach(function (group) {
            var tr = document.createElement('tr');
            tr.className = 'cabinet-pc-row';

            var tdToggle = document.createElement('td');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-xs btn-outline-secondary cabinet-pc-toggle';
            btn.setAttribute('aria-expanded', 'false');
            btn.title = 'Сайты по регионам';
            btn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            tdToggle.appendChild(btn);

            var tdPhrase = document.createElement('td');
            tdPhrase.className = 'cabinet-pc-phrase';
            tdPhrase.textContent = group.phrase || '';

            tr.appendChild(tdToggle);
            tr.appendChild(tdPhrase);
            tr.appendChild(renderEngineCell(group.yandex, 'yandex'));
            tr.appendChild(renderEngineCell(group.google, 'google'));
            resultsBody.appendChild(tr);

            var detailTr = renderSerpDetail(group);
            resultsBody.appendChild(detailTr);

            btn.addEventListener('click', function () {
                var open = !detailTr.classList.contains('d-none');
                detailTr.classList.toggle('d-none', open);
                btn.setAttribute('aria-expanded', open ? 'false' : 'true');
                btn.innerHTML = open
                    ? '<i class="fas fa-chevron-right"></i>'
                    : '<i class="fas fa-chevron-down"></i>';
            });
        });
    }

    function renderRows(rows) {
        lastRows = rows || [];
        groupedRows = groupRowsByPhrase(lastRows);
        syncFilterButtons();
        renderGroupedTable();
    }

    function showResults(payload) {
        if (!resultsWrap) {
            return;
        }
        resultsWrap.classList.remove('d-none');
        lastCost = payload.cost || 0;
        renderSummary(payload.summary || {});
        renderRows(payload.rows || []);
    }

    function copyFiltered() {
        var lines = filteredGroups.map(function (g) {
            return g.phrase || '';
        }).filter(Boolean);
        if (!lines.length) {
            setStatus('Нечего копировать', true);
            return;
        }
        var text = lines.join('\n');
        var done = function () {
            setStatus('Скопировано: ' + lines.length + ' фраз', false);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            done();
        } catch (e) {
            setStatus('Не удалось скопировать', true);
        }
        document.body.removeChild(ta);
    }

    function onFilterClick(btn) {
        var key = btn.getAttribute('data-filter');
        if (key === 'all') {
            filters.yandex = true;
            filters.google = true;
            filters.gz = false;
            filters.gnz = false;
            filters.com = false;
            filters.nocom = false;
        } else if (key === 'yandex' || key === 'google') {
            filters[key] = !filters[key];
            if (!filters.yandex && !filters.google) {
                filters[key] = true;
            }
        } else if (filters.hasOwnProperty(key)) {
            filters[key] = !filters[key];
        }
        syncFilterButtons();
        renderGroupedTable();
    }

    function buildFormBody(phraseText, opts) {
        opts = opts || {};
        var body = new FormData();
        body.append('_token', csrf);
        body.append('phrases', phraseText || '');
        body.append('yandex', engineYandex && engineYandex.checked ? '1' : '0');
        body.append('google', engineGoogle && engineGoogle.checked ? '1' : '0');
        var y = document.getElementById('cabinetPcYandexLr');
        var g = document.getElementById('cabinetPcGoogleLr');
        if (y) {
            body.append('yandex_lr', y.value || '');
        }
        if (g) {
            body.append('google_lr', g.value || '');
        }
        if (opts.yandexLr2) {
            body.append('yandex_lr2', opts.yandexLr2);
        }
        if (opts.googleLr2) {
            body.append('google_lr2', opts.googleLr2);
        }
        // История сохраняем одним пакетом после всех фраз.
        body.append('save', '0');
        return body;
    }

    function wantSaveHistory() {
        var saveEl = document.getElementById('cabinetPcSave');
        return canSave && saveEl && saveEl.checked;
    }

    function runAnalyze() {
        var phrases = listPhrases();
        if (enginesCount() === 0) {
            setStatus('Выберите хотя бы одну поисковую систему', true);
            return;
        }
        if (!phrases.length) {
            setStatus('Введите хотя бы одну фразу', true);
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
        }
        setStatus('Идёт расчёт…');
        progressStartedAt = Date.now();
        setProgressVisible(true);
        updateProgress(0, phrases.length, phrases[0]);
        progressTimer = setInterval(function () {
            if (progressTime) {
                progressTime.textContent = formatElapsed(Date.now() - progressStartedAt);
            }
        }, 500);

        var allRows = [];
        var totalCost = 0;
        var totalRequests = 0;
        var totalErrors = 0;
        var depth = 20;
        var contrast = { yandex: '', google: '' };
        var remaining = null;
        var idx = 0;

        function next() {
            if (idx >= phrases.length) {
                return finishOk();
            }
            var phrase = phrases[idx];
            updateProgress(idx, phrases.length, phrase);
            return postJson(
                analyzeUrl,
                buildFormBody(phrase, {
                    yandexLr2: contrast.yandex,
                    googleLr2: contrast.google,
                })
            )
                .then(function (pack) {
                    if (!pack.ok || !pack.data || !pack.data.ok) {
                        throw new Error((pack.data && pack.data.message) || 'Ошибка проверки');
                    }
                    var data = pack.data;
                    allRows = allRows.concat(data.rows || []);
                    totalCost += data.cost || 0;
                    totalRequests += data.requests || 0;
                    totalErrors += data.errors || 0;
                    if (data.depth) {
                        depth = data.depth;
                    }
                    if (data.remaining != null) {
                        remaining = data.remaining;
                    }
                    var cr = data.contrast_regions || {};
                    if (cr.yandex) {
                        contrast.yandex = String(cr.yandex);
                    }
                    if (cr.google) {
                        contrast.google = String(cr.google);
                    }
                    // fallback from rows
                    (data.rows || []).forEach(function (row) {
                        if (row.engine === 'yandex' && row.region_contrast && !contrast.yandex) {
                            contrast.yandex = String(row.region_contrast);
                        }
                        if (row.engine === 'google' && row.region_contrast && !contrast.google) {
                            contrast.google = String(row.region_contrast);
                        }
                    });
                    idx += 1;
                    updateProgress(idx, phrases.length, phrases[idx] || phrase);
                    return next();
                });
        }

        function finishOk() {
            var summary = buildSummaryClient(allRows);
            var payload = {
                cost: totalCost,
                requests: totalRequests,
                errors: totalErrors,
                depth: depth,
                summary: summary,
                rows: allRows,
                remaining: remaining,
            };

            var savePromise = Promise.resolve(null);
            if (wantSaveHistory() && historyStoreUrl) {
                if (progressTitle) {
                    progressTitle.textContent = 'Сохранение в историю…';
                }
                var body = new FormData();
                body.append('_token', csrf);
                body.append('phrases', phrases.join('\n'));
                body.append('rows', JSON.stringify(allRows));
                body.append('summary', JSON.stringify(summary));
                body.append('cost', String(totalCost));
                body.append('depth', String(depth));
                var engines = [];
                if (engineYandex && engineYandex.checked) {
                    engines.push('yandex');
                }
                if (engineGoogle && engineGoogle.checked) {
                    engines.push('google');
                }
                body.append('engines', engines.join(','));
                var y = document.getElementById('cabinetPcYandexLr');
                var g = document.getElementById('cabinetPcGoogleLr');
                if (y) {
                    body.append('yandex_lr', y.value || '');
                }
                if (g) {
                    body.append('google_lr', g.value || '');
                }
                if (contrast.yandex) {
                    body.append('yandex_lr2', contrast.yandex);
                }
                if (contrast.google) {
                    body.append('google_lr2', contrast.google);
                }
                savePromise = postJson(historyStoreUrl, body);
            }

            return savePromise
                .then(function (pack) {
                    finishProgress();
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    showResults(payload);
                    var historyId = pack && pack.data && pack.data.history_id;
                    var historyWarn =
                        pack && pack.ok === false
                            ? (pack.data && pack.data.message) || 'Не удалось сохранить историю'
                            : '';
                    setStatus(
                        'Готово' +
                            (historyId ? ' · сохранено #' + historyId : '') +
                            (historyWarn ? ' · ' + historyWarn : '') +
                            ' · ' +
                            formatElapsed(Date.now() - progressStartedAt),
                        !!historyWarn
                    );
                    if (remaining != null) {
                        root.setAttribute('data-remaining', String(remaining));
                    }
                    updateCostPreview();
                })
                .catch(function (err) {
                    finishProgress();
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    showResults(payload);
                    setStatus(
                        'Готово, но история не сохранена: ' + (err && err.message ? err.message : 'ошибка'),
                        true
                    );
                });
        }

        next().catch(function (err) {
            finishProgress();
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            if (allRows.length) {
                showResults({
                    cost: totalCost,
                    summary: buildSummaryClient(allRows),
                    rows: allRows,
                });
            }
            setStatus((err && err.message) || 'Ошибка сети или сервера', true);
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            runAnalyze();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (phrasesEl) {
                phrasesEl.value = '';
            }
            if (resultsWrap) {
                resultsWrap.classList.add('d-none');
            }
            setProgressVisible(false);
            setStatus('');
            updateCostPreview();
        });
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            if (!lastRows.length) {
                return;
            }
            var body = new FormData();
            body.append('_token', csrf);
            body.append('rows', JSON.stringify(lastRows));
            fetch(exportUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) {
                    return res.blob();
                })
                .then(function (blob) {
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'phrase-commerce.csv';
                    a.click();
                    URL.revokeObjectURL(a.href);
                });
        });
    }

    var copyBtn = document.getElementById('cabinetPcCopy');
    if (copyBtn) {
        copyBtn.addEventListener('click', copyFiltered);
    }

    var filtersWrap = document.getElementById('cabinetPcFilters');
    if (filtersWrap) {
        filtersWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-filter]');
            if (!btn || !filtersWrap.contains(btn)) {
                return;
            }
            onFilterClick(btn);
        });
    }

    if (engineYandex) {
        engineYandex.addEventListener('change', syncEngineRegions);
    }
    if (engineGoogle) {
        engineGoogle.addEventListener('change', syncEngineRegions);
    }
    if (phrasesEl) {
        phrasesEl.addEventListener('input', updateCostPreview);
    }

    document.querySelectorAll('.cabinet-pc-history-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tr = btn.closest('tr');
            var id = tr && tr.getAttribute('data-id');
            if (!id) {
                return;
            }
            setStatus('Загрузка истории…');
            fetch(historyBase + '/' + id, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.ok || !data.item) {
                        setStatus((data && data.message) || 'Не найдено', true);
                        return;
                    }
                    var item = data.item;
                    var results = item.results || {};
                    showResults({
                        cost: item.cost,
                        summary: results.summary || {},
                        rows: results.rows || [],
                    });
                    if (phrasesEl && item.params && Array.isArray(item.params.phrases)) {
                        phrasesEl.value = item.params.phrases.join('\n');
                    }
                    setStatus('Открыто из истории #' + id);
                    updateCostPreview();
                })
                .catch(function () {
                    setStatus('Ошибка загрузки истории', true);
                });
        });
    });

    document.querySelectorAll('.cabinet-pc-history-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tr = btn.closest('tr');
            var id = tr && tr.getAttribute('data-id');
            if (!id || !window.confirm('Удалить сохранённую проверку?')) {
                return;
            }
            fetch(historyBase + '/' + id, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            }).then(function () {
                if (tr) {
                    tr.remove();
                }
            });
        });
    });

    initRegionSelect(document.getElementById('cabinetPcYandexLr'));
    initRegionSelect(document.getElementById('cabinetPcGoogleLr'));
    syncEngineRegions();
    updateCostPreview();

    initTips(root);

    (function tryOpenHistoryFromUrl() {
        var match = window.location.search.match(/(?:\?|&)history=(\d+)/);
        if (!match || !historyBase) {
            return;
        }
        var id = match[1];
        var btn = document.querySelector('tr[data-id="' + id + '"] .cabinet-pc-history-open');
        if (btn) {
            btn.click();
        }
    })();
})();


