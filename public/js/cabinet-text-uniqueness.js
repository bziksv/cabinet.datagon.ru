(function () {
    'use strict';

    var root = document.getElementById('cabinetTuPage');
    if (!root) {
        return;
    }

    var analyzeUrl = root.getAttribute('data-analyze-url');
    var estimateUrl = root.getAttribute('data-estimate-url');
    var historyBase = root.getAttribute('data-history-url');
    var regionsUrl = root.getAttribute('data-regions-url');
    var csrf = root.getAttribute('data-csrf');
    var canSave = root.getAttribute('data-can-save') === '1';
    var minChars = parseInt(root.getAttribute('data-min-chars') || '200', 10) || 200;

    var form = document.getElementById('cabinetTuForm');
    var textEl = document.getElementById('cabinetTuText');
    var urlsEl = document.getElementById('cabinetTuUrls');
    var submitBtn = document.getElementById('cabinetTuSubmit');
    var clearBtn = document.getElementById('cabinetTuClear');
    var statusEl = document.getElementById('cabinetTuStatus');
    var progressEl = document.getElementById('cabinetTuProgress');
    var progressTitle = document.getElementById('cabinetTuProgressTitle');
    var progressSub = document.getElementById('cabinetTuProgressSub');
    var costText = document.getElementById('cabinetTuCostText');
    var costPreview = document.getElementById('cabinetTuCostPreview');
    var internetWrap = document.getElementById('cabinetTuInternetWrap');
    var urlsWrap = document.getElementById('cabinetTuUrlsWrap');
    var modeHint = document.getElementById('cabinetTuModeHint');
    var resultsWrap = document.getElementById('cabinetTuResultsWrap');
    var summaryEl = document.getElementById('cabinetTuSummary');
    var sourcesBody = document.querySelector('#cabinetTuSources tbody');
    var matchedEl = document.getElementById('cabinetTuMatched');

    function setStatus(text, isError) {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = text || '';
        statusEl.classList.toggle('text-danger', !!isError);
        statusEl.classList.toggle('text-muted', !isError);
    }

    function mode() {
        var el = document.querySelector('input[name="tu_mode"]:checked');
        return el && el.value === 'urls' ? 'urls' : 'internet';
    }

    function engine() {
        var el = document.querySelector('input[name="tu_engine"]:checked');
        return el && el.value === 'google' ? 'google' : 'yandex';
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

    function syncModeUi() {
        var m = mode();
        if (internetWrap) {
            internetWrap.classList.toggle('d-none', m !== 'internet');
        }
        if (urlsWrap) {
            urlsWrap.classList.toggle('d-none', m !== 'urls');
        }
        if (modeHint) {
            modeHint.textContent =
                m === 'urls'
                    ? root.getAttribute('data-hint-urls') || ''
                    : root.getAttribute('data-hint-internet') || '';
        }
        updateCostPreview();
    }

    function updateCostPreview() {
        if (!estimateUrl || !costText) {
            return;
        }
        var body = new FormData();
        body.append('_token', csrf);
        body.append('mode', mode());
        body.append('text', (textEl && textEl.value) || '');
        body.append('urls', (urlsEl && urlsEl.value) || '');
        fetch(estimateUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                var cost = (data && data.cost) || 0;
                var label = (costPreview && costPreview.getAttribute('data-label')) || 'Спишется';
                costText.innerHTML =
                    label + ' <strong id="cabinetTuCostValue">' + String(cost) + '</strong> ' + pluralLimit(cost);
            })
            .catch(function () {});
    }

    function setProgress(on, title, sub) {
        if (!progressEl) {
            return;
        }
        progressEl.classList.toggle('d-none', !on);
        if (progressTitle && title) {
            progressTitle.textContent = title;
        }
        if (progressSub) {
            progressSub.textContent = sub || '';
        }
    }

    function renderResult(result) {
        if (!resultsWrap || !result) {
            return;
        }
        resultsWrap.classList.remove('d-none');
        if (summaryEl) {
            summaryEl.innerHTML = '';
            var uniq = result.uniqueness_pct != null ? result.uniqueness_pct : 0;
            var tone = uniq >= 80 ? 'is-good' : uniq >= 50 ? 'is-mid' : 'is-low';
            var cards = [
                { t: 'Уникальность', v: uniq + '%', cls: tone },
                { t: 'Совпадения', v: (result.matched_pct || 0) + '%' },
                { t: 'Шинглы', v: (result.shingles_matched || 0) + ' / ' + (result.shingles_total || 0) },
                { t: 'Источники', v: (result.sources && result.sources.length) || 0 },
                { t: 'Списано', v: result.cost || 0 },
            ];
            cards.forEach(function (c) {
                var div = document.createElement('div');
                div.className = 'cabinet-tu-summary__card' + (c.cls ? ' ' + c.cls : '');
                div.innerHTML = '<span class="small text-secondary"></span><strong></strong>';
                div.querySelector('span').textContent = c.t;
                div.querySelector('strong').textContent = String(c.v);
                summaryEl.appendChild(div);
            });
        }

        if (sourcesBody) {
            sourcesBody.innerHTML = '';
            (result.sources || []).forEach(function (src) {
                var tr = document.createElement('tr');
                var tdUrl = document.createElement('td');
                if (src.url) {
                    var a = document.createElement('a');
                    a.href = src.url;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = src.url;
                    tdUrl.appendChild(a);
                } else {
                    tdUrl.textContent = '—';
                }
                var tdPct = document.createElement('td');
                tdPct.textContent = (src.overlap_pct != null ? src.overlap_pct : 0) + '%';
                var tdSamples = document.createElement('td');
                tdSamples.textContent = (src.samples || []).slice(0, 4).join(' · ');
                if (src.error) {
                    tdSamples.textContent = 'не удалось скачать';
                }
                tr.appendChild(tdUrl);
                tr.appendChild(tdPct);
                tr.appendChild(tdSamples);
                sourcesBody.appendChild(tr);
            });
        }

        if (matchedEl) {
            matchedEl.innerHTML = '';
            (result.matched_samples || []).forEach(function (s) {
                var chip = document.createElement('span');
                chip.className = 'cabinet-tu-chip';
                chip.textContent = s;
                matchedEl.appendChild(chip);
            });
            if (!(result.matched_samples || []).length) {
                matchedEl.textContent = 'Совпавших шинглов не найдено';
            }
        }
    }

    function initRegionSelect(el) {
        if (!el || !window.jQuery || !jQuery.fn.select2) {
            return;
        }
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
                    return { engine: 'yandex', q: params.term || '', limit: 30 };
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

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var text = (textEl && textEl.value) || '';
            if (text.replace(/\s+/g, ' ').trim().length < minChars) {
                setStatus('Текст слишком короткий (минимум ' + minChars + ' символов)', true);
                return;
            }
            if (mode() === 'urls') {
                var urls = ((urlsEl && urlsEl.value) || '').trim();
                if (!urls) {
                    setStatus('Укажите хотя бы один URL', true);
                    return;
                }
            }
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            setStatus('Идёт проверка…');
            setProgress(true, 'Проверка уникальности…', 'Собираем совпадения, подождите');

            var body = new FormData();
            body.append('_token', csrf);
            body.append('mode', mode());
            body.append('text', text);
            body.append('urls', (urlsEl && urlsEl.value) || '');
            body.append('engine', engine());
            var lr = document.getElementById('cabinetTuYandexLr');
            if (lr) {
                body.append('yandex_lr', lr.value || '');
            }
            var saveEl = document.getElementById('cabinetTuSave');
            body.append('save', canSave && saveEl && saveEl.checked ? '1' : '0');

            fetch(analyzeUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    });
                })
                .then(function (pack) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    setProgress(false);
                    if (!pack.ok || !pack.data || !pack.data.ok) {
                        setStatus((pack.data && pack.data.message) || 'Ошибка проверки', true);
                        return;
                    }
                    renderResult(pack.data.result || {});
                    setStatus(
                        'Готово' +
                            (pack.data.history_id ? ' · сохранено #' + pack.data.history_id : '') +
                            (pack.data.history_warning ? ' · ' + pack.data.history_warning : ''),
                        !!pack.data.history_warning
                    );
                    if (pack.data.remaining != null) {
                        root.setAttribute('data-remaining', String(pack.data.remaining));
                    }
                    updateCostPreview();
                })
                .catch(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    setProgress(false);
                    setStatus('Ошибка сети или сервера', true);
                });
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (textEl) {
                textEl.value = '';
            }
            if (urlsEl) {
                urlsEl.value = '';
            }
            if (resultsWrap) {
                resultsWrap.classList.add('d-none');
            }
            setProgress(false);
            setStatus('');
            updateCostPreview();
        });
    }

    document.querySelectorAll('input[name="tu_mode"]').forEach(function (el) {
        el.addEventListener('change', syncModeUi);
    });
    if (textEl) {
        textEl.addEventListener('input', updateCostPreview);
    }
    if (urlsEl) {
        urlsEl.addEventListener('input', updateCostPreview);
    }

    document.querySelectorAll('.cabinet-tu-history-open').forEach(function (btn) {
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
                    renderResult(data.item.results || {});
                    setStatus('Открыто из истории #' + id);
                })
                .catch(function () {
                    setStatus('Ошибка загрузки истории', true);
                });
        });
    });

    document.querySelectorAll('.cabinet-tu-history-del').forEach(function (btn) {
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

    initRegionSelect(document.getElementById('cabinetTuYandexLr'));
    syncModeUi();
    updateCostPreview();
})();
