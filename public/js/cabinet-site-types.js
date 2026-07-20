(function () {
    'use strict';

    var root = document.getElementById('cabinetStPage');
    if (!root) {
        return;
    }

    var analyzeUrl = root.getAttribute('data-analyze-url');
    var exportUrl = root.getAttribute('data-export-url');
    var historyBase = root.getAttribute('data-history-url');
    var regionsUrl = root.getAttribute('data-regions-url');
    var csrf = root.getAttribute('data-csrf');
    var canSave = root.getAttribute('data-can-save') === '1';
    var costUnit = parseInt(root.getAttribute('data-cost-unit') || '1', 10) || 1;

    var categories = [];
    try {
        categories = JSON.parse(root.getAttribute('data-categories') || '[]') || [];
    } catch (e) {
        categories = [];
    }

    var categoryMap = {};
    categories.forEach(function (c) {
        categoryMap[c.key] = c;
    });
    categoryMap.unknown = {
        key: 'unknown',
        label: 'Не определён',
        short: '?',
        color: '#94a3b8',
        hint: '',
    };

    var form = document.getElementById('cabinetStForm');
    var submitBtn = document.getElementById('cabinetStSubmit');
    var clearBtn = document.getElementById('cabinetStClear');
    var statusEl = document.getElementById('cabinetStStatus');
    var resultsWrap = document.getElementById('cabinetStResultsWrap');
    var resultsBody = document.querySelector('#cabinetStResults tbody');
    var resultsMeta = document.getElementById('cabinetStResultsMeta');
    var exportBtn = document.getElementById('cabinetStExport');
    var costValueEl = document.getElementById('cabinetStCostValue');
    var costPreviewEl = document.getElementById('cabinetStCostPreview');
    var costTextEl = document.getElementById('cabinetStCostText');
    var depthHidden = document.getElementById('cabinetStDepthValue');
    var phrasesEl = document.getElementById('cabinetStPhrases');
    var engineYandex = document.getElementById('engine_yandex');
    var engineGoogle = document.getElementById('engine_google');
    var yandexWrap = document.getElementById('cabinetStYandexRegionWrap');
    var googleWrap = document.getElementById('cabinetStGoogleRegionWrap');
    var filterSelect = document.getElementById('cabinetStFilterType');
    var queryTabs = document.getElementById('cabinetStQueryTabs');
    var shortfallNote = document.getElementById('cabinetStShortfallNote');
    var mixEl = document.getElementById('cabinetStMix');
    var verdictEl = document.getElementById('cabinetStVerdict');
    var verdictTitle = document.getElementById('cabinetStVerdictTitle');
    var verdictHint = document.getElementById('cabinetStVerdictHint');
    var phraseBlock = document.getElementById('cabinetStPhraseBlock');
    var phraseMatrix = document.getElementById('cabinetStPhraseMatrix');
    var hostsBlock = document.getElementById('cabinetStHostsBlock');
    var hostsBody = document.querySelector('#cabinetStFrequentHosts tbody');

    var lastPayload = null;
    var activeQueryIndex = 0;

    var TYPE_ORDER = [
        'ecommerce',
        'aggregators',
        'organizations',
        'content',
        'social',
        'reviews',
        'news',
        'games',
        'unknown',
    ];

    function setStatus(text, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = text || '';
        statusEl.classList.toggle('text-danger', !!isError);
        statusEl.classList.toggle('text-muted', !isError);
    }

    function countPhrases() {
        var raw = (phrasesEl && phrasesEl.value) || '';
        var lines = raw.split(/\r\n|\r|\n/);
        var seen = {};
        var n = 0;
        lines.forEach(function (line) {
            var p = String(line || '').replace(/\s+/g, ' ').trim();
            if (!p) {
                return;
            }
            var key = p.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            n += 1;
        });
        return n;
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

    function currentDepth() {
        return parseInt((depthHidden && depthHidden.value) || '10', 10) || 10;
    }

    /** Google: каждые 10 позиций = отдельный XML-запрос/лимит. */
    function googlePagesForDepth(depth) {
        return Math.max(1, Math.ceil(Math.max(1, depth) / 10));
    }

    function estimateCost() {
        var phrases = countPhrases();
        var cost = 0;
        if (engineYandex && engineYandex.checked) {
            cost += phrases;
        }
        if (engineGoogle && engineGoogle.checked) {
            cost += phrases * googlePagesForDepth(currentDepth());
        }
        return cost;
    }

    function pluralLimit(n) {
        var abs = Math.abs(n) % 100;
        var last = abs % 10;
        var one = (costPreviewEl && costPreviewEl.getAttribute('data-unit-one')) || 'лимит';
        var few = (costPreviewEl && costPreviewEl.getAttribute('data-unit-few')) || 'лимита';
        var many = (costPreviewEl && costPreviewEl.getAttribute('data-unit-many')) || 'лимитов';
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
        var cost = estimateCost();
        var label = (costPreviewEl && costPreviewEl.getAttribute('data-label')) || 'Спишется';
        if (costTextEl) {
            costTextEl.innerHTML =
                label + ' <strong id="cabinetStCostValue">' + String(cost) + '</strong> ' + pluralLimit(cost);
            costValueEl = document.getElementById('cabinetStCostValue');
        } else if (costValueEl) {
            costValueEl.textContent = String(cost);
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

    function initRegionSelect(selectEl) {
        if (!selectEl || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }
        var $el = window.jQuery(selectEl);
        var engine = selectEl.getAttribute('data-engine') || 'yandex';
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        $el.select2({
            theme: 'bootstrap4',
            placeholder: $el.data('placeholder') || 'Найти город или регион',
            allowClear: false,
            minimumInputLength: 0,
            width: '100%',
            dropdownParent: window.jQuery(document.body),
            language: {
                inputTooShort: function () {
                    return 'Введите название города или региона';
                },
                noResults: function () {
                    return 'Ничего не найдено';
                },
                searching: function () {
                    return 'Поиск…';
                },
            },
            ajax: {
                delay: 250,
                url: regionsUrl,
                dataType: 'json',
                data: function (params) {
                    return {
                        q: params.term || '',
                        limit: 25,
                        engine: engine,
                    };
                },
                processResults: function (data) {
                    return {
                        results: window.jQuery.map(data.results || [], function (item) {
                            return {
                                id: item.id,
                                text: item.text || item.name,
                                name: item.name,
                            };
                        }),
                    };
                },
            },
        });
    }

    function typeMeta(type) {
        return categoryMap[type] || categoryMap.unknown;
    }

    function renderMix(summary, cats) {
        if (!mixEl) {
            return;
        }
        mixEl.innerHTML = '';
        var counts = (summary && summary.counts) || {};
        var mix = (summary && summary.mix) || {};
        var order = (cats || []).map(function (c) {
            return c.key;
        }).concat(['unknown']);

        order.forEach(function (key) {
            var meta = typeMeta(key);
            if (cats && key !== 'unknown' && !categoryMap[key]) {
                return;
            }
            var count = parseInt(counts[key] || 0, 10) || 0;
            var pct = mix[key] != null ? mix[key] : 0;
            var card = document.createElement('div');
            card.className = 'cabinet-st-mix__card' + (count === 0 ? ' is-zero' : '');
            card.style.setProperty('--st-cat', meta.color || '#64748b');
            card.innerHTML =
                '<div class="cabinet-st-mix__name"></div>' +
                '<div class="cabinet-st-mix__value"></div>' +
                '<div class="cabinet-st-mix__bar"><span></span></div>';
            card.querySelector('.cabinet-st-mix__name').textContent = meta.label || key;
            card.querySelector('.cabinet-st-mix__value').textContent = pct + '% · ' + count;
            card.querySelector('.cabinet-st-mix__bar > span').style.width = Math.min(100, pct) + '%';
            mixEl.appendChild(card);
        });
    }

    function renderVerdict(verdict) {
        if (!verdictEl) {
            return;
        }
        var v = verdict || {};
        verdictEl.setAttribute('data-code', v.code || 'mixed');
        if (verdictTitle) {
            verdictTitle.textContent = v.label || '';
        }
        if (verdictHint) {
            verdictHint.textContent = v.hint || '';
        }
    }

    function fillFilterOptions(cats) {
        if (!filterSelect) {
            return;
        }
        var current = filterSelect.value;
        filterSelect.innerHTML = '';
        var all = document.createElement('option');
        all.value = '';
        all.textContent = 'Все типы';
        filterSelect.appendChild(all);

        var keys = (cats || []).map(function (c) {
            return c.key;
        }).concat(['unknown']);
        keys.forEach(function (key) {
            var meta = typeMeta(key);
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = meta.label || key;
            filterSelect.appendChild(opt);
        });
        filterSelect.value = current;
    }

    function requestedDepth() {
        if (lastPayload && lastPayload.depth) {
            return parseInt(lastPayload.depth, 10) || 10;
        }
        return currentDepth();
    }

    function queryRowCount(query) {
        return ((query && query.rows) || []).length;
    }

    function updateShortfallNote(query) {
        if (!shortfallNote) {
            return;
        }
        var got = queryRowCount(query);
        var want = requestedDepth();
        if (!query || got >= want || want <= 0) {
            shortfallNote.classList.add('d-none');
            shortfallNote.textContent = '';
            return;
        }
        var tpl =
            shortfallNote.getAttribute('data-template') ||
            'В этой вкладке :got из :want — так отдала поисковая система (или XML). Это не фильтр и не ошибка.';
        shortfallNote.textContent = tpl.replace(':got', String(got)).replace(':want', String(want));
        shortfallNote.classList.remove('d-none');
    }

    function renderQueryTabs(queries) {
        if (!queryTabs) {
            return;
        }
        queryTabs.innerHTML = '';
        var want = requestedDepth();
        queries.forEach(function (q, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            var got = queryRowCount(q);
            btn.className =
                'btn btn-sm btn-outline-primary cabinet-st-query-tabs__btn' +
                (idx === activeQueryIndex ? ' is-active' : '') +
                (got < want ? ' is-short' : '');
            var engineLabel = q.engine === 'google' ? 'G' : 'Я';
            btn.textContent = engineLabel + ' · ' + (q.phrase || '') + ' · ' + got;
            if (got < want) {
                btn.title =
                    (q.phrase || '') +
                    ' — в выдаче ' +
                    got +
                    ' из ' +
                    want +
                    ' (так отдала ПС/XML)';
            } else {
                btn.title = (q.phrase || '') + ' (' + (q.engine || '') + '), ' + got + ' поз.';
            }
            btn.addEventListener('click', function () {
                activeQueryIndex = idx;
                renderQueryTabs(queries);
                renderSerpTable(queries[idx]);
                updateShortfallNote(queries[idx]);
            });
            queryTabs.appendChild(btn);
        });
    }

    function renderSerpTable(query) {
        if (!resultsBody) {
            return;
        }
        resultsBody.innerHTML = '';
        var rows = (query && query.rows) || [];
        var filter = filterSelect ? filterSelect.value : '';

        rows.forEach(function (row) {
            var meta = typeMeta(row.type || 'unknown');
            var tr = document.createElement('tr');
            tr.setAttribute('data-type', row.type || 'unknown');
            if (filter && filter !== (row.type || 'unknown')) {
                tr.classList.add('is-filtered-out');
            }

            var tdPos = document.createElement('td');
            tdPos.className = 'cabinet-st-col-pos';
            tdPos.textContent = String(row.position || '');

            var tdDomain = document.createElement('td');
            tdDomain.className = 'cabinet-st-col-domain';
            tdDomain.textContent = row.domain || '';
            tdDomain.title = row.domain || '';

            var tdType = document.createElement('td');
            tdType.className = 'cabinet-st-col-type';
            var badge = document.createElement('span');
            badge.className = 'cabinet-st-type-badge';
            badge.style.setProperty('--st-cat', meta.color || '#64748b');
            badge.textContent = meta.label || row.type || '';
            if (row.type_source && row.type_source !== 'catalog') {
                badge.title = 'Источник: ' + row.type_source;
            } else if (row.in_catalog) {
                badge.title = 'Источник: каталог';
            }
            tdType.appendChild(badge);

            var tdUrl = document.createElement('td');
            tdUrl.className = 'cabinet-st-col-url';
            var a = document.createElement('a');
            a.href = row.url || '#';
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = row.url || '';
            a.title = row.url || '';
            a.className = 'cabinet-st-url-link';
            tdUrl.appendChild(a);

            tr.appendChild(tdPos);
            tr.appendChild(tdDomain);
            tr.appendChild(tdType);
            tr.appendChild(tdUrl);
            resultsBody.appendChild(tr);
        });
    }

    function orderedTypeKeys(cats) {
        var keys = [];
        TYPE_ORDER.forEach(function (k) {
            if (k === 'unknown') {
                return;
            }
            var found = cats.some(function (c) {
                return c.key === k;
            });
            if (found || categoryMap[k]) {
                keys.push(k);
            }
        });
        cats.forEach(function (c) {
            if (keys.indexOf(c.key) === -1 && c.key !== 'unknown') {
                keys.push(c.key);
            }
        });
        keys.push('unknown');
        return keys;
    }

    function renderPhraseMatrix(matrix, cats) {
        if (!phraseBlock || !phraseMatrix) {
            return;
        }
        var thead = phraseMatrix.querySelector('thead');
        var tbody = phraseMatrix.querySelector('tbody');
        if (!thead || !tbody) {
            return;
        }
        thead.innerHTML = '';
        tbody.innerHTML = '';

        if (!matrix || !matrix.length) {
            phraseBlock.classList.add('d-none');
            return;
        }

        phraseBlock.classList.remove('d-none');
        var keys = orderedTypeKeys(cats);

        var hr = document.createElement('tr');
        var thN = document.createElement('th');
        thN.textContent = '#';
        thN.className = 'cabinet-st-matrix__n';
        hr.appendChild(thN);
        var thP = document.createElement('th');
        thP.textContent = 'Ключевое слово';
        thP.className = 'cabinet-st-matrix__phrase';
        hr.appendChild(thP);
        keys.forEach(function (key) {
            var th = document.createElement('th');
            var meta = typeMeta(key);
            th.textContent = meta.short || meta.label || key;
            th.title = meta.label || key;
            th.className = 'cabinet-st-matrix__pct';
            hr.appendChild(th);
        });
        thead.appendChild(hr);

        matrix.forEach(function (row) {
            var tr = document.createElement('tr');
            var tdN = document.createElement('td');
            tdN.textContent = String(row.n || '');
            tdN.className = 'cabinet-st-matrix__n';
            tr.appendChild(tdN);
            var tdP = document.createElement('td');
            tdP.textContent = row.phrase || '';
            tdP.className = 'cabinet-st-matrix__phrase';
            tdP.title = row.phrase || '';
            tr.appendChild(tdP);
            var mix = row.mix || {};
            keys.forEach(function (key) {
                var td = document.createElement('td');
                td.className = 'cabinet-st-matrix__pct';
                var pct = mix[key] != null ? mix[key] : 0;
                td.textContent = String(pct) + '%';
                if (pct > 0) {
                    td.classList.add('is-hit');
                    var meta = typeMeta(key);
                    td.style.setProperty('--st-cat', meta.color || '#64748b');
                }
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    function renderFrequentHosts(hosts) {
        if (!hostsBlock || !hostsBody) {
            return;
        }
        hostsBody.innerHTML = '';
        if (!hosts || !hosts.length) {
            hostsBlock.classList.add('d-none');
            return;
        }
        hostsBlock.classList.remove('d-none');
        hosts.forEach(function (h) {
            var meta = typeMeta(h.type || 'unknown');
            var tr = document.createElement('tr');

            var tdHost = document.createElement('td');
            tdHost.textContent = h.host || '';

            var tdCount = document.createElement('td');
            tdCount.textContent = String(h.count || 0);
            tdCount.className = 'text-center';

            var tdIn = document.createElement('td');
            tdIn.textContent = h.in_catalog ? 'Да' : '—';
            tdIn.className = 'text-center';

            var tdType = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'cabinet-st-type-badge';
            badge.style.setProperty('--st-cat', meta.color || '#64748b');
            badge.textContent = meta.label || h.type || '';
            tdType.appendChild(badge);

            tr.appendChild(tdHost);
            tr.appendChild(tdCount);
            tr.appendChild(tdIn);
            tr.appendChild(tdType);
            hostsBody.appendChild(tr);
        });
    }

    function applyFilter() {
        if (!resultsBody) {
            return;
        }
        var filter = filterSelect ? filterSelect.value : '';
        Array.prototype.forEach.call(resultsBody.querySelectorAll('tr'), function (tr) {
            var type = tr.getAttribute('data-type') || 'unknown';
            tr.classList.toggle('is-filtered-out', !!(filter && filter !== type));
        });
    }

    function showResults(payload) {
        lastPayload = payload;
        if (!resultsWrap) {
            return;
        }
        resultsWrap.classList.remove('d-none');

        var summary = payload.summary || {};
        var queries = payload.queries || [];
        var cats = [];
        if (payload.categories) {
            Object.keys(payload.categories).forEach(function (key) {
                if (key === 'unknown') {
                    return;
                }
                var c = payload.categories[key];
                cats.push({
                    key: key,
                    label: c.label,
                    short: c.short,
                    color: c.color,
                    hint: c.hint,
                });
                categoryMap[key] = cats[cats.length - 1];
            });
            if (payload.categories.unknown) {
                categoryMap.unknown = Object.assign({ key: 'unknown' }, payload.categories.unknown);
            }
        } else {
            cats = categories.slice();
        }

        renderVerdict(summary.verdict);
        renderMix(summary, cats);
        fillFilterOptions(cats);
        renderPhraseMatrix(payload.phrase_matrix || [], cats);
        renderFrequentHosts(payload.frequent_hosts || []);

        activeQueryIndex = 0;
        renderQueryTabs(queries);
        if (queries.length) {
            renderSerpTable(queries[0]);
            updateShortfallNote(queries[0]);
        } else if (resultsBody) {
            resultsBody.innerHTML = '';
            updateShortfallNote(null);
        }

        if (resultsMeta) {
            resultsMeta.textContent =
                ' — ' +
                (summary.total_positions || 0) +
                ' поз. · ' +
                (summary.phrases || 0) +
                ' фраз · списано ' +
                (payload.cost || 0);
        }
    }

    function collectCustomDomains() {
        var data = {};
        Array.prototype.forEach.call(document.querySelectorAll('.cabinet-st-custom'), function (el) {
            var type = el.getAttribute('data-type');
            if (type) {
                data['custom_' + type] = el.value || '';
            }
        });
        return data;
    }

    function buildFormBody() {
        var body = new FormData();
        body.append('_token', csrf);
        body.append('phrases', (phrasesEl && phrasesEl.value) || '');
        body.append('depth', (depthHidden && depthHidden.value) || '10');
        body.append('yandex', engineYandex && engineYandex.checked ? '1' : '0');
        body.append('google', engineGoogle && engineGoogle.checked ? '1' : '0');

        var yandexSelect = document.getElementById('cabinetStYandexLr');
        var googleSelect = document.getElementById('cabinetStGoogleLr');
        if (yandexSelect) {
            body.append('yandex_lr', yandexSelect.value || '');
        }
        if (googleSelect) {
            body.append('google_lr', googleSelect.value || '');
        }

        var saveEl = document.getElementById('cabinetStSave');
        body.append('save', canSave && saveEl && saveEl.checked ? '1' : '0');

        var customs = collectCustomDomains();
        Object.keys(customs).forEach(function (key) {
            body.append(key, customs[key]);
        });

        return body;
    }

    function setLoading(loading) {
        if (submitBtn) {
            submitBtn.disabled = loading;
        }
        if (clearBtn) {
            clearBtn.disabled = loading;
        }
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (enginesCount() === 0) {
                setStatus('Выберите хотя бы одну поисковую систему', true);
                return;
            }
            if (countPhrases() === 0) {
                setStatus('Введите хотя бы одну фразу', true);
                return;
            }

            setLoading(true);
            setStatus('Сбор выдачи, открытие страниц и типизация…');

            fetch(analyzeUrl, {
                method: 'POST',
                body: buildFormBody(),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, status: res.status, data: data };
                    });
                })
                .then(function (pack) {
                    setLoading(false);
                    if (!pack.ok || !pack.data || !pack.data.ok) {
                        var msg =
                            (pack.data && pack.data.message) ||
                            'Не удалось выполнить проверку';
                        setStatus(msg, true);
                        return;
                    }
                    showResults(pack.data);
                    var warn = pack.data.history_warning;
                    setStatus(
                        'Готово' +
                            (pack.data.history_id ? ' · сохранено #' + pack.data.history_id : '') +
                            (warn ? ' · ' + warn : ''),
                        !!warn
                    );
                    if (pack.data.remaining != null) {
                        root.setAttribute('data-remaining', String(pack.data.remaining));
                    }
                    if (pack.data.saved_count != null) {
                        root.setAttribute('data-saved-count', String(pack.data.saved_count));
                    }
                })
                .catch(function () {
                    setLoading(false);
                    setStatus('Ошибка сети или сервера', true);
                });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (phrasesEl) {
                phrasesEl.value = '';
            }
            Array.prototype.forEach.call(document.querySelectorAll('.cabinet-st-custom'), function (el) {
                el.value = '';
            });
            if (resultsWrap) {
                resultsWrap.classList.add('d-none');
            }
            lastPayload = null;
            setStatus('');
            updateCostPreview();
        });
    }

    document.querySelectorAll('.cabinet-st-depth__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cabinet-st-depth__btn').forEach(function (b) {
                b.classList.remove('is-active');
            });
            btn.classList.add('is-active');
            if (depthHidden) {
                depthHidden.value = btn.getAttribute('data-depth') || '10';
            }
            updateCostPreview();
        });
    });

    if (engineYandex) {
        engineYandex.addEventListener('change', syncEngineRegions);
    }
    if (engineGoogle) {
        engineGoogle.addEventListener('change', syncEngineRegions);
    }
    if (phrasesEl) {
        phrasesEl.addEventListener('input', updateCostPreview);
    }
    if (filterSelect) {
        filterSelect.addEventListener('change', applyFilter);
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            if (!lastPayload || !lastPayload.queries) {
                return;
            }
            var body = new FormData();
            body.append('_token', csrf);
            body.append('queries', JSON.stringify(lastPayload.queries));
            fetch(exportUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('export');
                    }
                    return res.blob();
                })
                .then(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'site-types.csv';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                })
                .catch(function () {
                    setStatus('Не удалось выгрузить CSV', true);
                });
        });
    }

    document.querySelectorAll('.cabinet-st-history-open').forEach(function (btn) {
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
                    var queries = results.queries || [];
                    var looksLegacy = !results.phrase_matrix && queries.length && queries[0] && queries[0].rows &&
                        queries[0].rows.length && !queries[0].rows[0].type_source;
                    showResults({
                        ok: true,
                        cost: item.cost,
                        summary: results.summary || {},
                        phrase_matrix: results.phrase_matrix || [],
                        frequent_hosts: results.frequent_hosts || [],
                        queries: queries,
                        categories: results.categories || {},
                        depth: results.depth,
                    });
                    if (phrasesEl && item.params && Array.isArray(item.params.phrases)) {
                        phrasesEl.value = item.params.phrases.join('\n');
                    }
                    if (looksLegacy) {
                        setStatus(
                            'История #' + id + ' — старый прогон без проверки страниц. Нажмите «Определить типы» ещё раз.',
                            true
                        );
                    } else {
                        setStatus('Открыто из истории #' + id);
                    }
                    updateCostPreview();
                    resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(function () {
                    setStatus('Ошибка загрузки истории', true);
                });
        });
    });

    document.querySelectorAll('.cabinet-st-history-del').forEach(function (btn) {
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
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (data && data.ok && tr) {
                        tr.remove();
                    }
                });
        });
    });

    initRegionSelect(document.getElementById('cabinetStYandexLr'));
    initRegionSelect(document.getElementById('cabinetStGoogleLr'));
    syncEngineRegions();
    updateCostPreview();

    (function tryOpenHistoryFromUrl() {
        var match = window.location.search.match(/(?:\?|&)history=(\d+)/);
        if (!match || !historyBase) {
            return;
        }
        var id = match[1];
        var btn = document.querySelector('tr[data-id="' + id + '"] .cabinet-st-history-open');
        if (btn) {
            btn.click();
        }
    })();
})();
