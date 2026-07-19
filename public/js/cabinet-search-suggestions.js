(function () {
    'use strict';

    var root = document.getElementById('cabinetSsPage');
    if (!root) {
        return;
    }

    var collectUrl = root.getAttribute('data-collect-url');
    var exportUrl = root.getAttribute('data-export-url');
    var historyBase = root.getAttribute('data-history-url');
    var regionsUrl = root.getAttribute('data-regions-url');
    var csrf = root.getAttribute('data-csrf');
    var canSave = root.getAttribute('data-can-save') === '1';

    var form = document.getElementById('cabinetSsForm');
    var submitBtn = document.getElementById('cabinetSsSubmit');
    var clearBtn = document.getElementById('cabinetSsClear');
    var statusEl = document.getElementById('cabinetSsStatus');
    var resultsWrap = document.getElementById('cabinetSsResultsWrap');
    var resultsBody = document.querySelector('#cabinetSsResults tbody');
    var resultsMeta = document.getElementById('cabinetSsResultsMeta');
    var exportBtn = document.getElementById('cabinetSsExport');
    var copySuggestsBtn = document.getElementById('cabinetSsCopySuggests');
    var regionSelect = document.getElementById('cabinetSsYandexLr');
    var regionWrap = document.getElementById('cabinetSsYandexRegionWrap');
    var googleDomainWrap = document.getElementById('cabinetSsGoogleDomainWrap');
    var googleCountryWrap = document.getElementById('cabinetSsGoogleCountryWrap');
    var googleDomainSelect = document.getElementById('cabinetSsGoogleDomain');
    var googleCountrySelect = document.getElementById('cabinetSsGoogleGl');
    var engineYandex = document.getElementById('engine_yandex');
    var engineGoogle = document.getElementById('engine_google');

    var lastResults = [];

    function initRegionSelect() {
        if (!regionSelect || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }
        var $region = window.jQuery(regionSelect);
        if ($region.hasClass('select2-hidden-accessible')) {
            $region.select2('destroy');
        }
        $region.select2({
            theme: 'bootstrap4',
            placeholder: $region.data('placeholder') || 'Найти город или регион',
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

    function initGoogleCountrySelect() {
        if (!googleCountrySelect || typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }
        var $country = window.jQuery(googleCountrySelect);
        if ($country.hasClass('select2-hidden-accessible')) {
            $country.select2('destroy');
        }
        $country.select2({
            theme: 'bootstrap4',
            placeholder: $country.data('placeholder') || 'Страна Google',
            allowClear: false,
            width: '100%',
            dropdownParent: window.jQuery(document.body),
        });
    }

    function setSelectVisible(el, visible) {
        if (!el) {
            return;
        }
        el.classList.toggle('d-none', !visible);
        el.style.display = visible ? '' : 'none';
    }

    function syncEngineGeoFields() {
        var yandexOn = !!(engineYandex && engineYandex.checked);
        var googleOn = !!(engineGoogle && engineGoogle.checked);
        setSelectVisible(regionWrap, yandexOn);
        setSelectVisible(googleDomainWrap, googleOn);
        setSelectVisible(googleCountryWrap, googleOn);
    }

    function applyDomainDefaults() {
        if (!googleDomainSelect || !googleCountrySelect) {
            return;
        }
        var opt = googleDomainSelect.options[googleDomainSelect.selectedIndex];
        if (!opt) {
            return;
        }
        var gl = opt.getAttribute('data-gl') || '';
        if (!gl) {
            return;
        }
        if (window.jQuery && window.jQuery(googleCountrySelect).hasClass('select2-hidden-accessible')) {
            window.jQuery(googleCountrySelect).val(gl).trigger('change');
        } else {
            googleCountrySelect.value = gl;
        }
    }

    function currentGoogleHl() {
        if (!googleCountrySelect) {
            return 'ru';
        }
        var opt = googleCountrySelect.options[googleCountrySelect.selectedIndex];
        return (opt && opt.getAttribute('data-hl')) || 'ru';
    }

    initRegionSelect();
    initGoogleCountrySelect();
    syncEngineGeoFields();
    if (engineYandex) {
        engineYandex.addEventListener('change', syncEngineGeoFields);
    }
    if (engineGoogle) {
        engineGoogle.addEventListener('change', syncEngineGeoFields);
    }
    if (googleDomainSelect) {
        googleDomainSelect.addEventListener('change', applyDomainDefaults);
    }

    var QUICK_PRESETS = {
        basic: {
            modes: { phrase: true, space: false, en: false, ru: false, digits: false },
            presets: { local: false, shopping: false, questions: false, reviews: false },
            depth: 1,
        },
        alphabet: {
            modes: { phrase: true, space: true, en: true, ru: true, digits: true },
            presets: { local: false, shopping: false, questions: false, reviews: false },
            depth: 1,
        },
        commerce: {
            modes: { phrase: true, space: true, en: false, ru: false, digits: false },
            presets: { local: true, shopping: true, questions: false, reviews: false },
            depth: 1,
        },
        questions: {
            modes: { phrase: true, space: false, en: false, ru: false, digits: false },
            presets: { local: false, shopping: false, questions: true, reviews: true },
            depth: 1,
        },
        max: {
            modes: { phrase: true, space: true, en: true, ru: true, digits: true },
            presets: { local: true, shopping: true, questions: true, reviews: true },
            depth: 2,
        },
    };

    function setCheckbox(id, on) {
        var el = document.getElementById(id);
        if (el) {
            el.checked = !!on;
        }
    }

    function applyQuickPreset(name) {
        var cfg = QUICK_PRESETS[name];
        if (!cfg) {
            return;
        }
        setCheckbox('mode_phrase', cfg.modes.phrase);
        setCheckbox('mode_space', cfg.modes.space);
        setCheckbox('mode_en', cfg.modes.en);
        setCheckbox('mode_ru', cfg.modes.ru);
        setCheckbox('mode_digits', cfg.modes.digits);
        setCheckbox('preset_local', cfg.presets.local);
        setCheckbox('preset_shopping', cfg.presets.shopping);
        setCheckbox('preset_questions', cfg.presets.questions);
        setCheckbox('preset_reviews', cfg.presets.reviews);
        var depthEl = document.getElementById('cabinetSsDepth');
        if (depthEl) {
            depthEl.value = String(cfg.depth || 1);
        }
        document.querySelectorAll('.cabinet-ss-quick__btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-preset') === name);
        });
        setStatus('Пресет: ' + (document.querySelector('.cabinet-ss-quick__btn[data-preset="' + name + '"]') || {}).textContent || name, 'ok');
    }

    var quickWrap = document.getElementById('cabinetSsQuickPresets');
    if (quickWrap) {
        quickWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.cabinet-ss-quick__btn');
            if (!btn) {
                return;
            }
            applyQuickPreset(btn.getAttribute('data-preset'));
        });
    }

    function updateHeaderRemaining(left) {
        var header = document.getElementById('cabinet-header-module-limit');
        if (!header || left == null) {
            return;
        }
        var strong = header.querySelector('strong.ms-1');
        if (strong) {
            strong.textContent = left;
        }
        if (Number(left) <= 0) {
            var link = header.querySelector('.nav-link');
            if (link) {
                link.classList.add('text-danger');
                link.classList.remove('text-warning-emphasis');
            }
        }
    }

    function updateHeaderSaved(used) {
        var el = document.getElementById('cabinet-header-module-secondary-used');
        if (!el || used == null) {
            return;
        }
        el.textContent = used;
        var wrap = document.getElementById('cabinet-header-module-secondary');
        if (!wrap) {
            return;
        }
        var link = wrap.querySelector('.nav-link');
        var limitAttr = root.getAttribute('data-history-limit');
        var limit = limitAttr !== '' && limitAttr != null ? Number(limitAttr) : null;
        if (link && limit != null && !isNaN(limit) && Number(used) >= limit) {
            link.classList.add('text-danger');
            link.classList.remove('text-warning-emphasis');
        } else if (link) {
            link.classList.remove('text-danger');
            link.classList.add('text-warning-emphasis');
        }
    }

    function updateCounters(data) {
        if (data.remaining != null) {
            updateHeaderRemaining(data.remaining);
            root.setAttribute('data-remaining', String(data.remaining));
        }
        if (data.saved_count != null) {
            updateHeaderSaved(data.saved_count);
            root.setAttribute('data-saved-count', String(data.saved_count));
        }
    }

    var TYPE_LABELS = {
        exact: 'точное',
        append: 'дополнение',
        contains: 'вхождение',
        reorder: 'перестановка',
        prefix: 'в начале',
        suggest: 'подсказка',
        'точное': 'точное',
        'дополнение': 'дополнение',
        'вхождение': 'вхождение',
        'перестановка': 'перестановка',
        'в начале': 'в начале',
        'подсказка': 'подсказка',
    };

    function typeLabel(t) {
        if (!t) return '';
        return TYPE_LABELS[t] || t;
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject(new Error('copy failed'));
                }
            } catch (err) {
                reject(err);
            } finally {
                document.body.removeChild(ta);
            }
        });
    }

    function setStatus(text, kind) {
        statusEl.textContent = text || '';
        statusEl.className = 'small ml-2' + (kind ? ' is-' + kind : '');
    }

    function checked(id) {
        var el = document.getElementById(id);
        return !!(el && el.checked);
    }

    function payload() {
        return {
            seeds: document.getElementById('cabinetSsSeeds').value,
            stop_words: document.getElementById('cabinetSsStop').value,
            mode_phrase: checked('mode_phrase'),
            mode_space: checked('mode_space'),
            mode_en: checked('mode_en'),
            mode_ru: checked('mode_ru'),
            mode_digits: checked('mode_digits'),
            preset_local: checked('preset_local'),
            preset_shopping: checked('preset_shopping'),
            preset_questions: checked('preset_questions'),
            preset_reviews: checked('preset_reviews'),
            depth: document.getElementById('cabinetSsDepth').value,
            yandex: checked('engine_yandex'),
            google: checked('engine_google'),
            yandex_lr: regionSelect
                ? (window.jQuery && window.jQuery(regionSelect).hasClass('select2-hidden-accessible')
                    ? window.jQuery(regionSelect).val()
                    : regionSelect.value)
                : '213',
            google_domain: googleDomainSelect ? googleDomainSelect.value : 'google.ru',
            google_gl: googleCountrySelect
                ? (window.jQuery && window.jQuery(googleCountrySelect).hasClass('select2-hidden-accessible')
                    ? window.jQuery(googleCountrySelect).val()
                    : googleCountrySelect.value)
                : 'ru',
            google_hl: currentGoogleHl(),
            save: canSave && checked('cabinetSsSave'),
        };
    }

    function renderResults(rows) {
        lastResults = Array.isArray(rows) ? rows : [];
        resultsBody.innerHTML = '';
        lastResults.forEach(function (row) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + escapeHtml(row.seed || '') + '</td>' +
                '<td>' + escapeHtml(row.suggest || '') + '</td>' +
                '<td>' + escapeHtml(row.engine || '') + '</td>' +
                '<td>' + escapeHtml(String(row.level || '')) + '</td>' +
                '<td>' + escapeHtml(String(row.words || '')) + '</td>' +
                '<td>' + escapeHtml(typeLabel(row.type)) + '</td>';
            resultsBody.appendChild(tr);
        });
        resultsMeta.textContent = ' — ' + lastResults.length;
        resultsWrap.classList.toggle('d-none', lastResults.length === 0);
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        setStatus('Сбор…', 'busy');

        fetch(collectUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload()),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, status: r.status, data: data };
                });
            })
            .then(function (res) {
                submitBtn.disabled = false;
                if (!res.ok) {
                    setStatus((res.data && res.data.message) || 'Ошибка', 'error');
                    if (res.data) {
                        updateCounters(res.data);
                    }
                    return;
                }
                renderResults(res.data.results || []);
                updateCounters(res.data);
                var msg = 'Готово: ' + (res.data.results || []).length + ' подсказок, списано ' + (res.data.cost || 0);
                if (res.data.history_warning) {
                    msg += '. ' + res.data.history_warning;
                    setStatus(msg, 'error');
                } else {
                    setStatus(msg, 'ok');
                }
                if (res.data.history_id) {
                    // мягко обновим страницу истории при следующем заходе
                }
            })
            .catch(function () {
                submitBtn.disabled = false;
                setStatus('Сеть или сервер недоступны', 'error');
            });
    });

    clearBtn.addEventListener('click', function () {
        document.getElementById('cabinetSsSeeds').value = '';
        document.getElementById('cabinetSsStop').value = '';
        renderResults([]);
        setStatus('');
    });

    exportBtn.addEventListener('click', function () {
        if (!lastResults.length) {
            return;
        }
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = exportUrl;
        f.style.display = 'none';
        var token = document.createElement('input');
        token.name = '_token';
        token.value = csrf;
        f.appendChild(token);
        var input = document.createElement('input');
        input.name = 'results';
        input.value = JSON.stringify(lastResults);
        f.appendChild(input);
        document.body.appendChild(f);
        f.submit();
        document.body.removeChild(f);
    });

    if (copySuggestsBtn) {
        copySuggestsBtn.addEventListener('click', function () {
            if (!lastResults.length) {
                setStatus('Нет подсказок для копирования', 'error');
                return;
            }
            var lines = [];
            var seen = {};
            lastResults.forEach(function (row) {
                var s = String(row.suggest || '').trim();
                if (!s) return;
                var key = s.toLowerCase();
                if (seen[key]) return;
                seen[key] = true;
                lines.push(s);
            });
            copyText(lines.join('\n')).then(function () {
                setStatus('Скопировано подсказок: ' + lines.length, 'ok');
            }).catch(function () {
                setStatus('Не удалось скопировать в буфер', 'error');
            });
        });
    }

    root.addEventListener('click', function (e) {
        var openBtn = e.target.closest('.cabinet-ss-history-open');
        var delBtn = e.target.closest('.cabinet-ss-history-del');
        var tr = e.target.closest('tr[data-id]');
        if (!tr) {
            return;
        }
        var id = tr.getAttribute('data-id');

        if (openBtn) {
            setStatus('Загрузка истории…', 'busy');
            fetch(historyBase + '/' + id, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        setStatus(data.message || 'Не найдено', 'error');
                        return;
                    }
                    var params = data.item.params || {};
                    if (params.seeds) {
                        document.getElementById('cabinetSsSeeds').value = (params.seeds || []).join('\n');
                    }
                    if (params.stop_words) {
                        document.getElementById('cabinetSsStop').value = (params.stop_words || []).join('\n');
                    }
                    renderResults(data.item.results || []);
                    setStatus('История #' + id + ' открыта', 'ok');
                    resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(function () {
                    setStatus('Не удалось открыть историю', 'error');
                });
        }

        if (delBtn) {
            if (!window.confirm('Удалить сохранённую проверку?')) {
                return;
            }
            fetch(historyBase + '/' + id, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        tr.parentNode.removeChild(tr);
                        updateCounters(data);
                        setStatus('Удалено', 'ok');
                    }
                });
        }
    });
})();
