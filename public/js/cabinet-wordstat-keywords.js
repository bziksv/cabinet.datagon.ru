/**
 * Подбор слов Wordstat: список проектов и страница проекта.
 *
 * Корень #cabinetWskPage (data-* с URL), данные — <script type="application/json" id="cabinetWskData">.
 */
(function ($) {
    'use strict';

    var page = document.getElementById('cabinetWskPage');
    if (!page || typeof $ === 'undefined') {
        return;
    }

    var ds = page.dataset;
    var ROWS_LIMIT = 5000;
    var SOURCES = { left: 'Запрос с вашей фразой', right: 'Похожий запрос', manual: 'Ваша фраза' };
    var REASONS = {
        too_long: 'длиннее 400 символов',
        too_many_words: 'больше 7 слов',
        empty_group: 'это название группы, а под ним нет ни одной фразы. Фразы группы пишите строками ниже'
    };

    function fmt(n) {
        if (n === null || n === undefined || n === '') {
            return '';
        }
        return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
    function digits(v) { return parseInt(String(v || '').replace(/\D+/g, ''), 10) || 0; }
    function esc(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function toast(msg, isError) {
        if (window.CabinetAjaxForm && window.CabinetAjaxForm.toast) {
            window.CabinetAjaxForm.toast(msg, !!isError);
        }
    }
    function confirmAction(opts, onConfirm) {
        if (window.CabinetConfirm) {
            window.CabinetConfirm.show($.extend({ cancelLabel: 'Отмена' }, opts, { onConfirm: onConfirm }));
        } else if (window.confirm(opts.text)) {
            onConfirm();
        }
    }
    function modal(el) {
        if (window.bootstrap && window.bootstrap.Modal) {
            return window.bootstrap.Modal.getOrCreateInstance(el);
        }
        return { show: function () { $(el).modal('show'); }, hide: function () { $(el).modal('hide'); } };
    }
    function hasSelect2() { return typeof $.fn.select2 === 'function'; }

    function request(url, method, body) {
        return fetch(url, {
            method: method || 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': ds.csrf || ($('meta[name="csrf-token"]').attr('content') || ''),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false }; }).then(function (j) {
                if (!r.ok || j.ok === false) {
                    var msg = j.message;
                    if (j.errors) {
                        var first = Object.keys(j.errors)[0];
                        msg = first ? j.errors[first][0] : msg;
                    }
                    throw new Error(msg || 'Не удалось выполнить действие');
                }
                return j;
            });
        });
    }

    /* ---------- регионы (Select2 + ajax) ---------- */
    var regionNames = {};

    function regionSelect($el) {
        if (!hasSelect2()) {
            return;
        }
        var $modal = $el.closest('.modal');
        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            multiple: true,
            placeholder: 'Начните вводить регион',
            dropdownParent: $modal.length ? $modal : $(document.body),
            language: {
                inputTooShort: function () { return 'Введите название региона'; },
                noResults: function () { return 'Регион не найден'; },
                searching: function () { return 'Ищем…'; }
            },
            ajax: {
                url: ds.regionsUrl,
                dataType: 'json',
                delay: 200,
                data: function (p) { return { q: p.term || '', engine: 'yandex' }; },
                processResults: function (d) { return { results: d.results || [] }; }
            }
        });
        $el.on('select2:select', function (e) {
            if (e.params && e.params.data) {
                regionNames[String(e.params.data.id)] = e.params.data.text;
            }
        });
    }

    function plainSelect2($el, extra) {
        if (!hasSelect2() || !$el.length) {
            return;
        }
        var $modal = $el.closest('.modal');
        $el.select2($.extend({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $modal.length ? $modal : $(document.body)
        }, extra || {}));
    }

    function destroySelect2($el) {
        if ($el.length && $el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
    }

    $(document).on('input', '.cabinet-wsk-page .sa-num-space', function () {
        var n = digits(this.value);
        this.value = n ? fmt(n) : '';
    });

    /* ---------- список проектов: модалка создания ---------- */
    var createModal = document.getElementById('cabinetWskCreateModal');
    if (createModal) {
        $(createModal).on('shown.bs.modal', function () {
            regionSelect($(createModal).find('[data-wsk-region-select]'));
            plainSelect2($(createModal).find('[data-wsk-plain-select2]'), { placeholder: 'Только я', allowClear: true });
            $(createModal).find('input[name="domain"]').trigger('focus');
        }).on('hidden.bs.modal', function () {
            destroySelect2($(createModal).find('[data-wsk-region-select]'));
            destroySelect2($(createModal).find('[data-wsk-plain-select2]'));
        });
    }

    if (!ds.previewUrl) {
        return;
    }

    /* ======================= страница проекта ======================= */
    var dataEl = document.getElementById('cabinetWskData');
    var state = { groups: [], presets: [], activeRun: null, regions: [], defaults: {}, defaultGroup: 'Без группы' };
    try {
        state = $.extend(state, dataEl ? JSON.parse(dataEl.textContent) : {});
    } catch (e) { /* пустое состояние */ }
    (state.regions || []).forEach(function (r) { regionNames[String(r.id)] = r.text; });

    var phrases = [];
    var currentGroupId = state.groups.length ? state.groups[0].id : null;
    var status = 'active';
    var sort = { key: 'freq', dir: -1 };
    var pollTimer = null;
    var loadSeq = 0;

    function groupById(id) {
        return state.groups.filter(function (g) { return String(g.id) === String(id); })[0] || null;
    }

    /* ---------- форма настроек ---------- */
    function applySettings(s) {
        s = s || {};
        var $region = $('[data-wsk-setting="region_ids"]');
        $region.empty();
        (s.region_ids || []).forEach(function (id) {
            $region.append(new Option(regionNames[String(id)] || String(id), id, true, true));
        });
        $region.trigger('change');
        $('[data-wsk-setting="device"]').val(s.device || 'all');
        $('[data-wsk-setting="left_limit"]').val(fmt(s.left_limit));
        $('[data-wsk-setting="depth"]').val(String(s.depth || 0));
        $('[data-wsk-setting="depth_left_limit"]').val(fmt(s.depth_left_limit));
        $('[data-wsk-setting="depth_max_seeds"]').val(fmt(s.depth_max_seeds));
        $('[data-wsk-setting="minus_words"]').val((s.minus_words || []).join(', '));
        $('[data-wsk-setting="stop_words"]').val((s.stop_words || []).join(', '));
        $('[data-wsk-column]').each(function () { this.checked = (s.columns || []).indexOf(this.value) !== -1; });
    }

    function readSettings() {
        return {
            region_ids: $('[data-wsk-setting="region_ids"]').val() || [],
            device: $('[data-wsk-setting="device"]').val(),
            columns: $('[data-wsk-column]:checked').map(function () { return this.value; }).get(),
            left_limit: digits($('[data-wsk-setting="left_limit"]').val()),
            depth: parseInt($('[data-wsk-setting="depth"]').val(), 10) || 0,
            depth_left_limit: digits($('[data-wsk-setting="depth_left_limit"]').val()),
            depth_max_seeds: digits($('[data-wsk-setting="depth_max_seeds"]').val()),
            minus_words: $('[data-wsk-setting="minus_words"]').val(),
            stop_words: $('[data-wsk-setting="stop_words"]').val()
        };
    }

    /* ---------- модалка ввода названия ---------- */
    var promptEl = document.getElementById('cabinetWskPromptModal');
    var promptCallback = null;

    function askName(title, label, value, cb, teamId) {
        promptCallback = cb;
        $(promptEl).find('[data-wsk-prompt-title]').text(title);
        $(promptEl).find('[data-wsk-prompt-label]').text(label);
        $(promptEl).find('[data-wsk-prompt-input]').val(value || '');
        $(promptEl).find('[data-wsk-prompt-team-wrap]').prop('hidden', teamId === undefined);
        $(promptEl).find('[data-wsk-prompt-team]').val(teamId ? String(teamId) : '');
        modal(promptEl).show();
    }

    $(promptEl).on('shown.bs.modal', function () {
        $(promptEl).find('[data-wsk-prompt-input]').trigger('focus').trigger('select');
    }).on('hidden.bs.modal', function () {
        promptCallback = null;
    });

    $(document).on('submit', '[data-wsk-prompt-form]', function (e) {
        e.preventDefault();
        var value = $.trim($(promptEl).find('[data-wsk-prompt-input]').val());
        if (!value) {
            return;
        }
        var cb = promptCallback;
        var $team = $(promptEl).find('[data-wsk-prompt-team-wrap]:not([hidden]) [data-wsk-prompt-team]');
        var teamId = $team.length ? ($team.val() || null) : null;
        modal(promptEl).hide();
        if (cb) {
            cb(value, teamId);
        }
    });

    /* ---------- пресеты ---------- */
    function selectedPreset() {
        var id = $('[data-wsk-preset]').val();
        return state.presets.filter(function (p) { return String(p.id) === String(id); })[0] || null;
    }

    function syncPresetDelete() {
        var p = selectedPreset();
        $('[data-wsk-preset-delete]').prop('disabled', !(p && p.mine));
    }

    function renderPresets(selectedId) {
        var $p = $('[data-wsk-preset]');
        $p.empty().append(new Option('', ''));
        state.presets.slice().sort(function (a, b) { return String(a.title).localeCompare(String(b.title), 'ru'); })
            .forEach(function (p) {
                var label = p.title + (p.team_id ? ' · для команды' : '');
                $p.append(new Option(label, p.id, false, String(p.id) === String(selectedId)));
            });
        $p.trigger('change.select2');
        syncPresetDelete();
    }

    $(document).on('change', '[data-wsk-preset]', function () {
        var preset = selectedPreset();
        if (preset) {
            applySettings(preset.settings);
        }
        syncPresetDelete();
    });

    $(document).on('click', '[data-wsk-preset-save]', function () {
        var current = selectedPreset();
        var mine = current && current.mine ? current : null;
        askName('Сохранить настройки как шаблон', 'Название шаблона', mine ? mine.title : '', function (title, teamId) {
            var body = { title: title, settings: readSettings(), preset_id: mine && mine.title === title ? mine.id : null, team_id: teamId };
            request(ds.presetStoreUrl, 'POST', body).then(function (j) {
                state.presets = state.presets.filter(function (p) { return p.id !== j.preset.id; }).concat([j.preset]);
                renderPresets(j.preset.id);
                toast(j.message);
            }).catch(function (e) { toast(e.message, true); });
        }, mine ? (mine.team_id || '') : '');
    });

    $(document).on('click', '[data-wsk-preset-delete]', function () {
        var preset = selectedPreset();
        if (!preset || !preset.mine) {
            return;
        }
        confirmAction({ title: 'Удалить шаблон', text: 'Удалить шаблон «' + preset.title + '»? Сами фразы и группы он не трогает.', okLabel: 'Удалить', danger: true }, function () {
            request(ds.presetDeleteUrl + '/' + preset.id + '/delete', 'POST').then(function (j) {
                state.presets = state.presets.filter(function (p) { return p.id !== preset.id; });
                renderPresets(null);
                toast(j.message);
            }).catch(function (e) { toast(e.message, true); });
        });
    });

    /* ---------- проверка и запуск ---------- */
    function currentGroupName() {
        var g = groupById(currentGroupId);
        return g ? g.name : '';
    }

    function renderDefaultGroupHint() {
        var name = currentGroupName() || state.defaultGroup;
        $('[data-wsk-default-group-hint]').text('Фразы без группы (без «Группа:» и без «# Группа») пойдут в группу «' + name + '».');
    }

    function payload() {
        return { text: $('[data-wsk-text]').val(), group: currentGroupName(), settings: readSettings() };
    }

    $(document).on('click', '[data-wsk-preview-btn]', function () {
        var $btn = $(this).prop('disabled', true);
        request(ds.previewUrl, 'POST', payload()).then(function (j) {
            var html = '<div>Фраз пойдёт в сбор: <strong>' + fmt(j.seeds.length) + '</strong>, групп: <strong>' + fmt(j.groups.length) +
                '</strong>. Запросов к Wordstat примерно: <strong>' + fmt(j.estimate.total) + '</strong>';
            if (j.estimate.depth) {
                html += ' <span class="text-muted">(из них на расширение списка до ' + fmt(j.estimate.depth) + ')</span>';
            }
            html += '.</div>';
            if (j.groups.length) {
                html += '<div class="small text-muted mt-1">' + j.groups.map(esc).join(', ') + '</div>';
            }
            if (j.rejected.length) {
                html += '<div class="text-danger small mt-1">Пропущено строк: ' + j.rejected.length + '<ul class="mb-0 ps-3">' + j.rejected.map(function (r) {
                    return '<li>«' + esc(r.line) + '» — ' + (REASONS[r.reason] || r.reason) + '</li>';
                }).join('') + '</ul></div>';
            }
            $('[data-wsk-preview]').html(html).prop('hidden', false);
        }).catch(function (e) { toast(e.message, true); }).finally(function () { $btn.prop('disabled', false); });
    });

    $(document).on('click', '[data-wsk-run-btn]', function () {
        var $btn = $(this).prop('disabled', true);
        request(ds.runUrl, 'POST', payload()).then(function (j) {
            toast(j.message);
            showRun(j.run);
        }).catch(function (e) {
            toast(e.message, true);
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '[data-wsk-cancel]', function () {
        var run = state.activeRun;
        if (!run) {
            return;
        }
        confirmAction({ title: 'Остановить сбор', text: 'Остановить сбор? Фразы, которые уже собраны, останутся в группах.', okLabel: 'Остановить', danger: true }, function () {
            request(ds.runStatusUrl + '/' + run.id + '/cancel', 'POST').then(function (j) {
                showRun(j.run);
                toast(j.message);
            }).catch(function (e) { toast(e.message, true); });
        });
    });

    function showRun(run) {
        state.activeRun = run;
        var $box = $('[data-wsk-progress]').prop('hidden', false);
        var pct = run.seeds_total ? Math.min(100, Math.round(run.seeds_done * 100 / run.seeds_total)) : 0;
        if (run.status === 'done') {
            pct = 100;
        }
        $box.find('.progress-bar').css('width', pct + '%')
            .toggleClass('progress-bar-striped progress-bar-animated', !!run.active)
            .toggleClass('bg-danger', run.status === 'failed')
            .toggleClass('bg-secondary', run.status === 'cancelled');
        var text = 'Проверено фраз: ' + fmt(run.seeds_done) + ' из ' + fmt(run.seeds_total) +
            ' · новых фраз: ' + fmt(run.phrases_added) + ' · запросов к Wordstat: ' + fmt(run.requests_used);
        if (run.status === 'queued') { text = 'В очереди. ' + text; }
        if (run.status === 'done') { text = 'Готово. ' + text; }
        if (run.status === 'cancelled') { text = 'Остановлено. ' + text; }
        if (run.seeds_failed > 0) { text += ' · не удалось проверить: ' + fmt(run.seeds_failed); }
        if (run.status === 'failed') { text = (run.error || 'Сбор не удался') + '. ' + text; }
        $box.find('[data-wsk-progress-text]').text(text);
        $box.find('[data-wsk-cancel]').prop('hidden', !run.active);
        $('[data-wsk-run-btn]').prop('disabled', !!run.active);

        clearTimeout(pollTimer);
        if (run.active) {
            pollTimer = setTimeout(function () {
                request(ds.runStatusUrl + '/' + run.id).then(function (j) {
                    var wasDone = j.run.seeds_done !== run.seeds_done;
                    showRun(j.run);
                    if (wasDone && j.run.active) {
                        reloadGroups();
                    }
                }).catch(function () {
                    pollTimer = setTimeout(function () { showRun(run); }, 5000);
                });
            }, 3000);
        } else {
            reloadGroups();
        }
    }

    /* ---------- группы ---------- */
    function reloadGroups() {
        request(ds.groupsUrl)
            .then(function (j) {
                state.groups = j.groups || [];
                if (!groupById(currentGroupId)) {
                    currentGroupId = state.groups.length ? state.groups[0].id : null;
                }
                renderGroups();
                loadPhrases();
            })
            .catch(function () { /* следующий опрос обновит */ });
    }

    function renderGroups() {
        var canMerge = state.groups.length > 1;
        var html = state.groups.map(function (g) {
            return '<div class="cabinet-wsk-group' + (String(g.id) === String(currentGroupId) ? ' is-active' : '') + '" data-group-id="' + g.id + '">' +
                '<button type="button" class="cabinet-wsk-group__name" data-wsk-group-open>' + esc(g.name) + '</button>' +
                '<span class="cabinet-wsk-group__count">' + fmt(g.count) + '</span>' +
                '<span class="cabinet-wsk-group__actions">' +
                '<button type="button" class="btn btn-link btn-sm p-0" data-wsk-group-rename aria-label="Переименовать группу"><i class="bi bi-pencil"></i></button>' +
                (canMerge ? '<button type="button" class="btn btn-link btn-sm p-0" data-wsk-group-merge aria-label="Объединить с другой группой"><i class="bi bi-intersect"></i></button>' : '') +
                '<button type="button" class="btn btn-link btn-sm p-0 text-danger" data-wsk-group-delete aria-label="Удалить группу"><i class="bi bi-trash"></i></button>' +
                '</span></div>';
        }).join('');
        $('[data-wsk-groups]').html(html || '<div class="text-muted small">Групп пока нет. Вставьте фразы в блок 1 и нажмите «Запустить сбор».</div>');

        var $bg = $('[data-wsk-bulk-group]');
        var selected = $bg.val();
        $bg.empty();
        state.groups.forEach(function (g) {
            if (String(g.id) !== String(currentGroupId)) {
                $bg.append(new Option(g.name, g.id, false, String(g.id) === String(selected)));
            }
        });
        $bg.trigger('change.select2');

        var $exp = $('[data-wsk-export-group]');
        if (currentGroupId) {
            $exp.attr('href', ds.exportUrl + '?group_id=' + encodeURIComponent(currentGroupId)).prop('hidden', false);
        } else {
            $exp.prop('hidden', true);
        }
        renderDefaultGroupHint();
    }

    $(document).on('click', '[data-wsk-group-open]', function () {
        currentGroupId = parseInt($(this).closest('[data-group-id]').attr('data-group-id'), 10);
        $('[data-wsk-check-all]').prop('checked', false);
        renderGroups();
        loadPhrases();
    });

    $(document).on('click', '[data-wsk-group-rename]', function () {
        var g = groupById($(this).closest('[data-group-id]').attr('data-group-id'));
        if (!g) {
            return;
        }
        askName('Переименовать группу', 'Название группы', g.name, function (name) {
            if (name === g.name) {
                return;
            }
            request(ds.groupsUrl + '/' + g.id, 'POST', { name: name }).then(function (j) {
                g.name = j.group.name;
                renderGroups();
                toast('Группа переименована');
            }).catch(function (e) { toast(e.message, true); });
        });
    });

    $(document).on('click', '[data-wsk-group-delete]', function () {
        var g = groupById($(this).closest('[data-group-id]').attr('data-group-id'));
        if (!g) {
            return;
        }
        confirmAction({
            title: 'Удалить группу',
            text: 'Удалить группу «' + g.name + '» вместе с фразами (' + fmt(g.count) + ')?',
            okLabel: 'Удалить',
            danger: true
        }, function () {
            request(ds.groupsUrl + '/' + g.id + '/delete', 'POST').then(function (j) {
                toast(j.message);
                reloadGroups();
            }).catch(function (e) { toast(e.message, true); });
        });
    });

    var mergeEl = document.getElementById('cabinetWskMergeModal');
    var mergeSourceId = null;

    $(document).on('click', '[data-wsk-group-merge]', function () {
        var g = groupById($(this).closest('[data-group-id]').attr('data-group-id'));
        if (!g) {
            return;
        }
        mergeSourceId = g.id;
        var $t = $(mergeEl).find('[data-wsk-merge-target]').empty().append(new Option('', ''));
        state.groups.forEach(function (x) {
            if (String(x.id) !== String(g.id)) {
                $t.append(new Option(x.name + ' (' + fmt(x.count) + ')', x.id));
            }
        });
        $(mergeEl).find('[data-wsk-merge-text]').text('Фразы группы «' + g.name + '» перейдут в выбранную группу, а сама группа будет удалена.');
        modal(mergeEl).show();
    });

    $(mergeEl).on('shown.bs.modal', function () {
        plainSelect2($(mergeEl).find('[data-wsk-merge-target]'), { placeholder: 'Выберите группу' });
    }).on('hidden.bs.modal', function () {
        destroySelect2($(mergeEl).find('[data-wsk-merge-target]'));
    });

    $(document).on('submit', '[data-wsk-merge-form]', function (e) {
        e.preventDefault();
        var targetId = parseInt($(mergeEl).find('[data-wsk-merge-target]').val(), 10);
        if (!targetId || !mergeSourceId || String(targetId) === String(mergeSourceId)) {
            toast('Выберите другую группу для объединения', true);
            return;
        }
        var sourceId = mergeSourceId;
        request(ds.groupsUrl + '/' + sourceId + '/merge', 'POST', { target_id: targetId }).then(function (j) {
            modal(mergeEl).hide();
            if (String(currentGroupId) === String(sourceId)) {
                currentGroupId = targetId;
            }
            toast(j.message);
            reloadGroups();
        }).catch(function (err) { toast(err.message, true); });
    });

    /* ---------- рабочие / минус ---------- */
    $(document).on('click', '[data-wsk-status]', function () {
        status = $(this).attr('data-wsk-status');
        $('[data-wsk-status]').removeClass('active');
        $(this).addClass('active');
        $('[data-wsk-bulk-action] option[data-wsk-for-status]').each(function () {
            this.hidden = this.getAttribute('data-wsk-for-status') !== status;
        });
        $('[data-wsk-bulk-action]').val('').trigger('change');
        $('[data-wsk-check-all]').prop('checked', false);
        loadPhrases();
    });

    /* ---------- таблица фраз ---------- */
    function loadPhrases() {
        var seq = ++loadSeq;
        if (!currentGroupId) {
            phrases = [];
            renderRows();
            return;
        }
        request(ds.phrasesUrl + '?group_id=' + encodeURIComponent(currentGroupId) + '&status=' + status).then(function (j) {
            if (seq !== loadSeq) {
                return;
            }
            phrases = j.phrases || [];
            renderRows();
        }).catch(function (e) { toast(e.message, true); });
    }

    function visibleRows() {
        var q = String($('[data-wsk-search]').val() || '').toLowerCase().trim();
        var rows = phrases.filter(function (p) { return !q || String(p.phrase).toLowerCase().indexOf(q) !== -1; });
        rows.sort(function (a, b) {
            if (sort.key === 'phrase') {
                return sort.dir * String(a.phrase).localeCompare(String(b.phrase), 'ru');
            }
            return sort.dir * ((a.freq || 0) - (b.freq || 0));
        });
        return rows;
    }

    function renderRows() {
        var rows = visibleRows();
        var shown = rows.slice(0, ROWS_LIMIT);
        $('[data-wsk-rows]').html(shown.map(function (p) {
            return '<tr><td class="cabinet-wsk-check"><input type="checkbox" class="form-check-input" data-wsk-check value="' + p.id + '" aria-label="Выбрать фразу"></td>' +
                '<td>' + esc(p.phrase) + '</td>' +
                '<td class="text-end cabinet-wsk-num">' + fmt(p.freq) + '</td>' +
                '<td class="text-muted small">' + esc(SOURCES[p.source] || p.source) + (p.depth ? ' · из расширения, круг ' + fmt(p.depth) : '') + '</td></tr>';
        }).join(''));

        var $empty = $('[data-wsk-empty]');
        if (!currentGroupId) {
            $empty.text('Выберите группу слева, чтобы увидеть её фразы.').prop('hidden', false);
        } else if (!phrases.length) {
            $empty.text(status === 'minus' ? 'В исключённых пока ничего нет.' : 'В группе пока нет фраз.').prop('hidden', false);
        } else if (!rows.length) {
            $empty.text('Ничего не найдено.').prop('hidden', false);
        } else if (rows.length > ROWS_LIMIT) {
            $empty.text('Показаны первые ' + fmt(ROWS_LIMIT) + ' фраз. Уточните поиск или скачайте Excel.').prop('hidden', false);
        } else {
            $empty.prop('hidden', true);
        }

        $('[data-wsk-sort]').each(function () {
            var key = $(this).attr('data-wsk-sort');
            $(this).find('.cabinet-wsk-sort-mark').text(key === sort.key ? (sort.dir > 0 ? '↑' : '↓') : '');
        });
        $('[data-wsk-check-all]').prop('checked', false);
        updateCounter();
    }

    function updateCounter() {
        var total = phrases.length;
        var visible = $('[data-wsk-check]').length;
        var checked = $('[data-wsk-check]:checked').length;
        var text = total ? ('Фраз: ' + fmt(total)) : '';
        if (total && visible !== total) {
            text += ' · показано ' + fmt(visible);
        }
        if (checked) {
            text += ' · выбрано ' + fmt(checked);
        }
        $('[data-wsk-counter]').text(text);
    }

    var searchTimer = null;
    $(document).on('input', '[data-wsk-search]', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(renderRows, 150);
    });
    $(document).on('click', '[data-wsk-sort]', function () {
        var key = $(this).attr('data-wsk-sort');
        sort = { key: key, dir: sort.key === key ? -sort.dir : (key === 'phrase' ? 1 : -1) };
        renderRows();
    });
    $(document).on('change', '[data-wsk-check-all]', function () {
        $('[data-wsk-check]').prop('checked', this.checked);
        updateCounter();
    });
    $(document).on('change', '[data-wsk-check]', updateCounter);
    $(document).on('change', '[data-wsk-bulk-action]', function () {
        $('[data-wsk-bulk-group-wrap]').prop('hidden', this.value !== 'move');
    });

    $(document).on('click', '[data-wsk-bulk-apply]', function () {
        var action = $('[data-wsk-bulk-action]').val();
        var ids = $('[data-wsk-check]:checked').map(function () { return parseInt(this.value, 10); }).get();
        if (!action) {
            toast('Выберите действие', true);
            return;
        }
        if (!ids.length) {
            toast('Отметьте фразы в таблице', true);
            return;
        }
        var groupId = parseInt($('[data-wsk-bulk-group]').val(), 10) || null;
        if (action === 'move' && !groupId) {
            toast('Выберите группу, куда перенести фразы', true);
            return;
        }
        var run = function () {
            request(ds.bulkUrl, 'POST', { ids: ids, action: action, group_id: groupId }).then(function (j) {
                toast('Готово: ' + fmt(j.affected));
                reloadGroups();
            }).catch(function (e) { toast(e.message, true); });
        };
        if (action === 'delete') {
            confirmAction({ title: 'Удалить фразы', text: 'Удалить выбранные фразы (' + fmt(ids.length) + ')?', okLabel: 'Удалить', danger: true }, run);
        } else {
            run();
        }
    });

    /* ---------- запуск ---------- */
    $('[data-wsk-region-select]').each(function () { regionSelect($(this)); });
    plainSelect2($('[data-wsk-preset]'), { placeholder: 'Не выбран', allowClear: true });
    plainSelect2($('[data-wsk-bulk-group]'), { placeholder: 'Группа' });
    applySettings(state.defaults);
    renderPresets(null);
    renderGroups();
    loadPhrases();
    if (state.activeRun) {
        showRun(state.activeRun);
    }
})(window.jQuery);
