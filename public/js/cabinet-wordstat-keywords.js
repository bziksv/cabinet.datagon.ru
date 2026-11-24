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
    var PER_PAGE = 200;
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
    var page = 1;
    var total = 0;
    var lastPage = 1;
    var currentGroupId = state.groups.length ? state.groups[0].id : null;
    var viewIds = currentGroupId ? [currentGroupId] : [];
    var NEW_GROUP = 'new';
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
                        reloadGroups(false);
                    }
                }).catch(function () {
                    pollTimer = setTimeout(function () { showRun(run); }, 5000);
                });
            }, 3000);
        } else {
            reloadGroups(true);
        }
    }

    /* ---------- группы ---------- */
    function reloadGroups(refreshTable) {
        if (refreshTable === undefined) {
            refreshTable = true;
        }
        request(ds.groupsUrl)
            .then(function (j) {
                state.groups = j.groups || [];
                syncSelection();
                renderGroups();
                if (refreshTable || total === 0) {
                    loadPhrases();
                }
            })
            .catch(function () { /* следующий опрос обновит */ });
    }

    function isViewed(id) {
        return viewIds.some(function (v) { return String(v) === String(id); });
    }

    function syncSelection() {
        viewIds = viewIds.filter(function (id) { return !!groupById(id); });
        if (!groupById(currentGroupId)) {
            currentGroupId = viewIds.length ? viewIds[0] : (state.groups.length ? state.groups[0].id : null);
        }
        if (!viewIds.length && currentGroupId) {
            viewIds = [currentGroupId];
        }
    }

    function openGroups(ids, current) {
        viewIds = ids;
        if (current) {
            currentGroupId = current;
        }
        syncSelection();
        page = 1;
        $('[data-wsk-check-all]').prop('checked', false);
        renderGroups();
        loadPhrases(1);
    }

    function renderGroups() {
        var canMerge = state.groups.length > 1;
        var all = state.groups.length > 1 && viewIds.length === state.groups.length;
        var total = state.groups.reduce(function (sum, g) { return sum + (g.count || 0); }, 0);
        var head = state.groups.length > 1
            ? '<div class="cabinet-wsk-group cabinet-wsk-group--all' + (all ? ' is-active' : '') + '">' +
                '<button type="button" class="cabinet-wsk-group__name" data-wsk-group-all>Все группы</button>' +
                '<span class="cabinet-wsk-group__count">' + fmt(total) + '</span></div>'
            : '';
        var html = head + state.groups.map(function (g) {
            return '<div class="cabinet-wsk-group' + (isViewed(g.id) ? ' is-active' : '') + '" data-group-id="' + g.id + '">' +
                (state.groups.length > 1 ? '<input type="checkbox" class="form-check-input mt-0" data-wsk-group-check aria-label="Показать вместе с другими"' + (isViewed(g.id) ? ' checked' : '') + '>' : '') +
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
        $bg.empty().append(new Option('', ''));
        $bg.append(new Option('+ Новая группа…', NEW_GROUP, false, selected === NEW_GROUP));
        state.groups.forEach(function (g) {
            if (!(viewIds.length === 1 && isViewed(g.id))) {
                $bg.append(new Option(g.name, g.id, false, String(g.id) === String(selected)));
            }
        });
        $bg.trigger('change.select2');

        var $exp = $('[data-wsk-export-group]');
        if (viewIds.length) {
            $exp.attr('href', ds.exportUrl + '?group_ids=' + encodeURIComponent(viewIds.join(','))).prop('hidden', false);
            $exp.find('[data-wsk-export-label]').text(viewIds.length > 1 ? 'Excel: выбранные группы (' + fmt(viewIds.length) + ')' : 'Excel: только эта группа');
        } else {
            $exp.prop('hidden', true);
        }
        renderDefaultGroupHint();
    }

    $(document).on('click', '[data-wsk-group-open]', function () {
        var id = parseInt($(this).closest('[data-group-id]').attr('data-group-id'), 10);
        openGroups([id], id);
    });

    $(document).on('change', '[data-wsk-group-check]', function () {
        var id = parseInt($(this).closest('[data-group-id]').attr('data-group-id'), 10);
        var ids = viewIds.filter(function (v) { return String(v) !== String(id); });
        if (this.checked) {
            ids.push(id);
        } else if (!ids.length) {
            this.checked = true;
            return;
        }
        openGroups(ids, this.checked ? id : (String(currentGroupId) === String(id) ? ids[0] : currentGroupId));
    });

    $(document).on('click', '[data-wsk-group-all]', function () {
        openGroups(state.groups.map(function (g) { return g.id; }), currentGroupId);
    });

    function createGroup(cb) {
        askName('Новая группа', 'Название группы', '', function (name) {
            request(ds.groupsUrl, 'POST', { name: name }).then(function (j) {
                state.groups.push(j.group);
                toast(j.message);
                cb(j.group);
            }).catch(function (e) { toast(e.message, true); });
        });
    }

    $(document).on('click', '[data-wsk-group-create]', function () {
        createGroup(function (g) { openGroups([g.id], g.id); });
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
            if (isViewed(sourceId) && !isViewed(targetId)) {
                viewIds.push(targetId);
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
        loadPhrases(1);
    });

    /* ---------- таблица фраз ---------- */
    function loadPhrases(targetPage) {
        if (targetPage !== undefined) {
            page = Math.max(1, parseInt(targetPage, 10) || 1);
        }
        var seq = ++loadSeq;
        if (!viewIds.length) {
            phrases = [];
            total = 0;
            lastPage = 1;
            page = 1;
            renderRows();
            return;
        }
        var q = String($('[data-wsk-search]').val() || '').trim();
        var params = [
            'group_ids=' + encodeURIComponent(viewIds.join(',')),
            'status=' + encodeURIComponent(status),
            'page=' + encodeURIComponent(page),
            'per_page=' + encodeURIComponent(PER_PAGE),
            'sort=' + encodeURIComponent(sort.key),
            'dir=' + (sort.dir > 0 ? 'asc' : 'desc')
        ];
        if (q) {
            params.push('q=' + encodeURIComponent(q));
        }
        request(ds.phrasesUrl + '?' + params.join('&')).then(function (j) {
            if (seq !== loadSeq) {
                return;
            }
            phrases = j.phrases || [];
            total = typeof j.total === 'number' ? j.total : phrases.length;
            page = typeof j.page === 'number' ? j.page : 1;
            lastPage = typeof j.last_page === 'number' ? j.last_page : 1;
            renderRows();
            if (targetPage !== undefined) {
                var $scroll = $('.cabinet-wsk-table__scroll');
                if ($scroll.length) {
                    $scroll[0].scrollTop = 0;
                }
            }
        }).catch(function (e) { toast(e.message, true); });
    }

    function groupName(id) {
        var g = groupById(id);
        return g ? g.name : '';
    }

    function renderRows() {
        var multi = viewIds.length > 1;
        var q = String($('[data-wsk-search]').val() || '').trim();
        $('[data-wsk-group-col]').prop('hidden', !multi);
        $('[data-wsk-rows]').html(phrases.map(function (p) {
            return '<tr><td class="cabinet-wsk-check"><input type="checkbox" class="form-check-input" data-wsk-check value="' + p.id + '" aria-label="Выбрать фразу"></td>' +
                '<td>' + esc(p.phrase) + '</td>' +
                (multi ? '<td class="text-muted small">' + esc(groupName(p.group_id)) + '</td>' : '') +
                '<td class="text-end cabinet-wsk-num">' + fmt(p.freq) + '</td>' +
                '<td class="text-muted small">' + esc(SOURCES[p.source] || p.source) + (p.depth ? ' · из расширения, круг ' + fmt(p.depth) : '') + '</td></tr>';
        }).join(''));

        var $empty = $('[data-wsk-empty]');
        if (!viewIds.length) {
            $empty.text('Выберите группу слева, чтобы увидеть её фразы.').prop('hidden', false);
        } else if (!total && q) {
            $empty.text('По запросу «' + q + '» ничего не найдено.').prop('hidden', false);
        } else if (!total) {
            $empty.text(status === 'minus' ? 'В исключённых пока ничего нет.' : (multi ? 'В выбранных группах пока нет фраз.' : 'В группе пока нет фраз. Перенесите сюда фразы из другой группы: отметьте их и выберите «Перенести в группу».')).prop('hidden', false);
        } else {
            $empty.prop('hidden', true);
        }

        $('[data-wsk-sort]').each(function () {
            var key = $(this).attr('data-wsk-sort');
            $(this).find('.cabinet-wsk-sort-mark').text(key === sort.key ? (sort.dir > 0 ? '↑' : '↓') : '');
        });
        $('[data-wsk-check-all]').prop('checked', false);
        renderPager();
        updateCounter();
    }

    function renderPager() {
        var $pager = $('[data-wsk-pager]');
        if (!total || lastPage <= 1) {
            $pager.prop('hidden', true);
            return;
        }
        var from = (page - 1) * PER_PAGE + 1;
        var to = Math.min(total, from + phrases.length - 1);
        $pager.prop('hidden', false);
        $('[data-wsk-pager-info]').text('Показано ' + fmt(from) + '–' + fmt(to) + ' из ' + fmt(total));
        $('[data-wsk-pager-num]').text('Стр. ' + fmt(page) + ' из ' + fmt(lastPage));
        $('[data-wsk-page="first"], [data-wsk-page="prev"]').prop('disabled', page <= 1);
        $('[data-wsk-page="next"], [data-wsk-page="last"]').prop('disabled', page >= lastPage);
    }

    function updateCounter() {
        var q = String($('[data-wsk-search]').val() || '').trim();
        var checked = $('[data-wsk-check]:checked').length;
        var text = total ? ((q ? 'Найдено: ' : 'Фраз: ') + fmt(total)) : '';
        if (checked) {
            text += ' · выбрано ' + fmt(checked);
        }
        $('[data-wsk-counter]').text(text);
    }

    var searchTimer = null;
    $(document).on('input', '[data-wsk-search]', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadPhrases(1); }, 250);
    });
    $(document).on('click', '[data-wsk-sort]', function () {
        var key = $(this).attr('data-wsk-sort');
        sort = { key: key, dir: sort.key === key ? -sort.dir : (key === 'phrase' || key === 'group' ? 1 : -1) };
        loadPhrases(1);
    });
    $(document).on('click', '[data-wsk-page]', function () {
        var kind = $(this).attr('data-wsk-page');
        var next = page;
        if (kind === 'first') { next = 1; }
        else if (kind === 'prev') { next = Math.max(1, page - 1); }
        else if (kind === 'next') { next = Math.min(lastPage, page + 1); }
        else if (kind === 'last') { next = lastPage; }
        if (next !== page) {
            loadPhrases(next);
        }
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
        var rawGroup = $('[data-wsk-bulk-group]').val();
        var groupId = parseInt(rawGroup, 10) || null;
        if (action === 'move' && !groupId && rawGroup !== NEW_GROUP) {
            toast('Выберите группу, куда перенести фразы', true);
            return;
        }
        var run = function (targetId) {
            request(ds.bulkUrl, 'POST', { ids: ids, action: action, group_id: targetId || groupId }).then(function (j) {
                toast('Готово: ' + fmt(j.affected));
                reloadGroups();
            }).catch(function (e) { toast(e.message, true); });
        };
        if (action === 'move' && rawGroup === NEW_GROUP) {
            createGroup(function (g) {
                $('[data-wsk-bulk-group]').val('');
                run(g.id);
            });
        } else if (action === 'delete') {
            confirmAction({ title: 'Удалить фразы', text: 'Удалить выбранные фразы (' + fmt(ids.length) + ')?', okLabel: 'Удалить', danger: true }, function () { run(); });
        } else {
            run();
        }
    });

    /* ---------- запуск ---------- */
    $('[data-wsk-region-select]').each(function () { regionSelect($(this)); });
    plainSelect2($('[data-wsk-preset]'), { placeholder: 'Не выбран', allowClear: true });
    plainSelect2($('[data-wsk-bulk-group]'), { placeholder: 'Куда перенести' });
    applySettings(state.defaults);
    renderPresets(null);
    renderGroups();
    loadPhrases();
    if (state.activeRun) {
        showRun(state.activeRun);
    }
})(window.jQuery);
