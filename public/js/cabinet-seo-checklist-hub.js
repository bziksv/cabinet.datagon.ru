(function () {
    var STORAGE_KEY = 'seoChecklistDefaultTemplateId';

    var SEARCH_SYNONYMS = {
        домен: ['domain', 'dns', 'whois', 'ssl', 'ns', 'mx'],
        dns: ['домен', 'domain', 'ns', 'mx', 'spf'],
        аптайм: ['uptime', 'доступность', 'мониторинг сайта'],
        uptime: ['аптайм', 'доступность'],
        позиции: ['position', 'мониторинг позиций', 'выдача'],
        метрика: ['metrika', 'яндекс', 'цели', 'визиты'],
        вебмастер: ['webmaster', 'яндекс'],
        gsc: ['google', 'search console', 'покрытие'],
        google: ['gsc', 'search console'],
        ux: ['интерфейс', 'юзабилити', 'мобил'],
        интерфейс: ['ux', 'юзабилити'],
        ssl: ['сертификат', 'https', 'домен'],
        https: ['ssl', 'сертификат'],
        robots: ['robots.txt', 'sitemap'],
        sitemap: ['карта сайта', 'robots'],
        title: ['тайтл', 'мета'],
        description: ['дескрипшн', 'мета'],
        ссылк: ['links', 'донор', 'анкор', 'ссылоч'],
        контент: ['статьи', 'текст', 'faq'],
        важн: ['important'],
        повтор: ['monthly', 'weekly', 'recurring', 'ежемесяч', 'еженед'],
        мониторинг: ['позиции', 'аптайм', 'домен', 'uptime', 'domain'],
    };

    function normalizeSearch(str) {
        return String(str || '')
            .toLowerCase()
            .replace(/ё/g, 'е')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function tokenizeSearch(query) {
        return normalizeSearch(query).split(' ').filter(Boolean);
    }

    function tokenMatches(hay, token) {
        if (!token) return true;
        if (hay.indexOf(token) !== -1) return true;

        // Точное совпадение ключа синонима
        var alts = SEARCH_SYNONYMS[token];
        if (alts) {
            for (var i = 0; i < alts.length; i++) {
                if (hay.indexOf(alts[i]) !== -1) return true;
            }
            return false;
        }

        // Префикс только если токен ≥ 3 символов и ключ начинается с токена («ссыл» → «ссылк»)
        // Не наоборот: иначе «ссылки» через короткий ключ раздувает выдачу
        if (token.length < 3) return false;
        var keys = Object.keys(SEARCH_SYNONYMS);
        for (var k = 0; k < keys.length; k++) {
            var key = keys[k];
            if (key.indexOf(token) !== 0) continue;
            if (hay.indexOf(key) !== -1) return true;
            var syns = SEARCH_SYNONYMS[key];
            for (var s = 0; s < syns.length; s++) {
                if (hay.indexOf(syns[s]) !== -1) return true;
            }
        }
        // Стем: «ссылки» → ищем «ссылк» в hay, если есть ключ-префикс
        for (var j = 0; j < keys.length; j++) {
            var stem = keys[j];
            if (stem.length >= 4 && token.indexOf(stem) === 0) {
                if (hay.indexOf(stem) !== -1) return true;
                var stemSyns = SEARCH_SYNONYMS[stem];
                for (var t = 0; t < stemSyns.length; t++) {
                    if (hay.indexOf(stemSyns[t]) !== -1) return true;
                }
            }
        }
        return false;
    }

    function smartMatch(haystack, query) {
        var tokens = tokenizeSearch(query);
        if (!tokens.length) return true;
        var hay = normalizeSearch(haystack);
        for (var i = 0; i < tokens.length; i++) {
            if (!tokenMatches(hay, tokens[i])) return false;
        }
        return true;
    }

    // export helpers for show-page script if present
    window.cabinetSeoChecklistSearch = {
        smartMatch: smartMatch,
        normalizeSearch: normalizeSearch,
    };

    function readDefaultTemplateId() {
        try {
            return localStorage.getItem(STORAGE_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    function writeDefaultTemplateId(id) {
        try {
            if (id) localStorage.setItem(STORAGE_KEY, String(id));
            else localStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* ignore */ }
    }

    function applyDefaultToSelect(select) {
        if (!select) return;
        var saved = readDefaultTemplateId();
        if (!saved) return;
        var option = select.querySelector('option[value="' + saved + '"]');
        if (option) select.value = saved;
    }

    document.querySelectorAll('[data-sc-template-select]').forEach(function (select) {
        applyDefaultToSelect(select);
        select.addEventListener('change', function () {
            writeDefaultTemplateId(select.value);
        });
    });

    document.querySelectorAll('[data-sc-create-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var select = form.querySelector('[data-sc-template-select]');
            if (select && select.value) writeDefaultTemplateId(select.value);
        });
    });

    // —— Create modal: searchable site select (Select2) ——
    (function initDomainSelect2() {
        if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }
        var $ = window.jQuery;
        var $select = $('[data-sc-domain-select]');
        if (!$select.length) return;

        var $modal = $('#cabinetScCreateModal');
        var placeholder = $select.attr('data-placeholder') || '';

        function initSelect2() {
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: $modal.length ? $modal : $(document.body),
                language: {
                    noResults: function () {
                        return 'Ничего не найдено';
                    },
                    searching: function () {
                        return 'Поиск…';
                    },
                },
            });
        }

        if ($modal.length) {
            $modal.on('shown.bs.modal', function () {
                initSelect2();
                $select.val(null).trigger('change');
            });
            $modal.on('hidden.bs.modal', function () {
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
            });
        } else {
            initSelect2();
        }
    })();

    document.querySelectorAll('[data-sc-set-default-template]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var id = btn.getAttribute('data-sc-set-default-template');
            writeDefaultTemplateId(id);
            document.querySelectorAll('[data-sc-set-default-template]').forEach(function (other) {
                other.classList.toggle('is-default', other === btn);
                var label = other.querySelector('[data-sc-default-label]');
                if (label) {
                    label.textContent = other === btn
                        ? (other.getAttribute('data-label-default') || 'Default')
                        : (other.getAttribute('data-label-make-default') || 'Make default');
                }
            });
        });
    });

    // Mark current default on templates page
    var savedId = readDefaultTemplateId();
    if (savedId) {
        document.querySelectorAll('[data-sc-set-default-template]').forEach(function (btn) {
            var isDefault = btn.getAttribute('data-sc-set-default-template') === savedId;
            btn.classList.toggle('is-default', isDefault);
            var label = btn.querySelector('[data-sc-default-label]');
            if (label) {
                label.textContent = isDefault
                    ? (btn.getAttribute('data-label-default') || 'Default')
                    : (btn.getAttribute('data-label-make-default') || 'Make default');
            }
        });
    }

    function applyProjectFilters() {
        var qEl = document.querySelector('[data-sc-project-search]');
        var roleEl = document.querySelector('[data-sc-project-role-filter]');
        var sortEl = document.querySelector('[data-sc-project-sort]');
        var emptyEl = document.querySelector('[data-sc-project-empty]');
        var grid = document.querySelector('[data-sc-project-grid]');
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-sc-project-card]'));
        if (!cards.length) return;

        var q = (qEl && qEl.value ? qEl.value : '').toLowerCase().trim();
        var role = roleEl ? roleEl.value : '';
        var sort = sortEl ? sortEl.value : 'activity';
        var visible = 0;

        cards.sort(function (a, b) {
            if (sort === 'progress') {
                return (parseInt(a.getAttribute('data-sort-progress'), 10) || 0) - (parseInt(b.getAttribute('data-sort-progress'), 10) || 0);
            }
            if (sort === 'domain') {
                return String(a.getAttribute('data-sort-domain') || '').localeCompare(String(b.getAttribute('data-sort-domain') || ''));
            }
            return (parseInt(b.getAttribute('data-sort-activity'), 10) || 0) - (parseInt(a.getAttribute('data-sort-activity'), 10) || 0);
        });

        if (grid) {
            cards.forEach(function (card) {
                grid.appendChild(card);
            });
        }

        cards.forEach(function (card) {
            var hay = (card.getAttribute('data-search') || '').toLowerCase();
            var hasPm = card.getAttribute('data-has-pm') === '1';
            var ok = true;
            if (q && hay.indexOf(q) === -1) ok = false;
            if (ok && role === 'no-pm' && hasPm) ok = false;
            card.classList.toggle('is-filtered-out', !ok);
            if (ok) visible++;
        });

        if (emptyEl) emptyEl.classList.toggle('d-none', visible > 0);
    }

    var searchEl = document.querySelector('[data-sc-project-search]');
    var roleFilterEl = document.querySelector('[data-sc-project-role-filter]');
    var sortEl = document.querySelector('[data-sc-project-sort]');
    if (searchEl) searchEl.addEventListener('input', applyProjectFilters);
    if (roleFilterEl) roleFilterEl.addEventListener('change', applyProjectFilters);
    if (sortEl) sortEl.addEventListener('change', applyProjectFilters);

    // —— Team hub: search + collapse/expand ——
    var TEAM_OPEN_KEY = 'seoChecklistTeamsOpen';

    function readTeamOpenMap() {
        try {
            return JSON.parse(sessionStorage.getItem(TEAM_OPEN_KEY) || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    function writeTeamOpenMap(map) {
        try {
            sessionStorage.setItem(TEAM_OPEN_KEY, JSON.stringify(map || {}));
        } catch (e) { /* ignore */ }
    }

    function teamCards() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-sc-team-card]'));
    }

    function restoreTeamOpenState() {
        var map = readTeamOpenMap();
        var cards = teamCards();
        if (!cards.length) return;
        cards.forEach(function (card) {
            var id = card.getAttribute('data-team-id');
            if (!id) return;
            // По умолчанию свёрнуто — список читается как список, не как стена форм
            if (map[id] === true) card.setAttribute('open', '');
            else card.removeAttribute('open');
            card.addEventListener('toggle', function () {
                var next = readTeamOpenMap();
                next[id] = !!card.open;
                writeTeamOpenMap(next);
            });
        });
    }

    function applyTeamFilters() {
        var qEl = document.querySelector('[data-sc-team-search]');
        var emptyEl = document.querySelector('[data-sc-team-empty]');
        var cards = teamCards();
        if (!cards.length) return;
        var q = (qEl && qEl.value ? qEl.value : '').toLowerCase().trim();
        var visible = 0;
        cards.forEach(function (card) {
            var hay = (card.getAttribute('data-search') || '').toLowerCase();
            var ok = !q || hay.indexOf(q) !== -1;
            card.classList.toggle('is-filtered-out', !ok);
            if (ok) visible++;
        });
        if (emptyEl) emptyEl.classList.toggle('d-none', visible > 0);
    }

    document.querySelectorAll('[data-sc-stop-toggle]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });

    var teamSearchEl = document.querySelector('[data-sc-team-search]');
    if (teamSearchEl) teamSearchEl.addEventListener('input', applyTeamFilters);

    var teamsExpand = document.querySelector('[data-sc-teams-expand]');
    var teamsCollapse = document.querySelector('[data-sc-teams-collapse]');
    if (teamsExpand) {
        teamsExpand.addEventListener('click', function () {
            var map = readTeamOpenMap();
            teamCards().forEach(function (card) {
                card.setAttribute('open', '');
                var id = card.getAttribute('data-team-id');
                if (id) map[id] = true;
            });
            writeTeamOpenMap(map);
        });
    }
    if (teamsCollapse) {
        teamsCollapse.addEventListener('click', function () {
            var map = readTeamOpenMap();
            teamCards().forEach(function (card) {
                card.removeAttribute('open');
                var id = card.getAttribute('data-team-id');
                if (id) map[id] = false;
            });
            writeTeamOpenMap(map);
        });
    }
    restoreTeamOpenState();

    // —— Assign projects: search + collapse list ——
    var ASSIGN_COLLAPSED_KEY = 'seoChecklistAssignCollapsed';

    function applyAssignFilters() {
        var qEl = document.querySelector('[data-sc-assign-project-search]');
        var emptyEl = document.querySelector('[data-sc-assign-empty]');
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-sc-assign-row]'));
        if (!rows.length) return;
        var q = (qEl && qEl.value ? qEl.value : '').toLowerCase().trim();
        var visible = 0;
        rows.forEach(function (row) {
            var hay = (row.getAttribute('data-search') || '').toLowerCase();
            var ok = !q || hay.indexOf(q) !== -1;
            row.classList.toggle('is-filtered-out', !ok);
            if (ok) visible++;
        });
        if (emptyEl) emptyEl.classList.toggle('d-none', visible > 0);
    }

    var assignSearchEl = document.querySelector('[data-sc-assign-project-search]');
    if (assignSearchEl) assignSearchEl.addEventListener('input', applyAssignFilters);

    var assignPanel = document.querySelector('[data-sc-assign-panel]');
    var assignExpand = document.querySelector('[data-sc-assign-expand]');
    var assignCollapse = document.querySelector('[data-sc-assign-collapse]');

    function setAssignCollapsed(collapsed) {
        if (!assignPanel) return;
        assignPanel.classList.toggle('is-collapsed', !!collapsed);
        try {
            sessionStorage.setItem(ASSIGN_COLLAPSED_KEY, collapsed ? '1' : '0');
        } catch (e) { /* ignore */ }
    }

    if (assignPanel) {
        try {
            if (sessionStorage.getItem(ASSIGN_COLLAPSED_KEY) === '1') {
                assignPanel.classList.add('is-collapsed');
            }
        } catch (e) { /* ignore */ }
    }
    if (assignExpand) assignExpand.addEventListener('click', function () { setAssignCollapsed(false); });
    if (assignCollapse) assignCollapse.addEventListener('click', function () { setAssignCollapsed(true); });

    // —— Template editor: smart search ——
    (function initTemplateSmartSearch() {
        var bar = document.querySelector('[data-sc-tpl-search-bar]');
        if (!bar) return;

        var input = bar.querySelector('[data-sc-tpl-search]');
        var countEl = bar.querySelector('[data-sc-tpl-search-count]');
        var emptyEl = document.querySelector('[data-sc-tpl-empty]');
        var chipImportant = bar.querySelector('[data-sc-tpl-chip="important"]');
        var chipRepeat = bar.querySelector('[data-sc-tpl-chip="repeat"]');
        var expandBtn = bar.querySelector('[data-sc-tpl-expand]');
        var collapseBtn = bar.querySelector('[data-sc-tpl-collapse]');
        var chipState = { important: false, repeat: false };

        function tasks() {
            return Array.prototype.slice.call(document.querySelectorAll('[data-sc-tpl-task]'));
        }

        function apply() {
            var q = input ? input.value : '';
            var list = tasks();
            var visible = 0;

            list.forEach(function (task) {
                var hay = task.getAttribute('data-search') || '';
                var ok = smartMatch(hay, q);
                if (ok && chipState.important && task.getAttribute('data-important') !== '1') ok = false;
                if (ok && chipState.repeat && task.getAttribute('data-repeat') !== '1') ok = false;
                task.classList.toggle('is-filtered-out', !ok);
                if (ok) visible++;
            });

            var filtering = !!(q && String(q).trim()) || chipState.important || chipState.repeat;

            document.querySelectorAll('[data-sc-tpl-stage]').forEach(function (stage) {
                var stageTasks = stage.querySelectorAll('[data-sc-tpl-task]');
                var shown = stage.querySelectorAll('[data-sc-tpl-task]:not(.is-filtered-out)').length;
                var total = stageTasks.length;
                var meta = stage.querySelector('[data-sc-tpl-stage-meta]');
                // Пустые этапы (без задач) не прячем — там форма «Добавить задачу»
                stage.classList.toggle('is-empty-filter', filtering && shown === 0);
                if (meta) {
                    meta.textContent = filtering ? (shown + '/' + total) : String(total);
                }
                if (filtering && shown > 0) stage.open = true;
                var addRow = stage.querySelector('[data-sc-tpl-add]');
                if (addRow) addRow.classList.toggle('is-filtered-out', filtering);
            });

            if (countEl) {
                var totalAll = list.length;
                countEl.textContent = filtering
                    ? (visible + ' / ' + totalAll)
                    : (totalAll ? (totalAll + '') : '');
            }
            if (emptyEl) {
                // «Ничего не найдено» — только при активном фильтре
                emptyEl.classList.toggle('d-none', !filtering || visible > 0);
            }
        }

        if (input) {
            input.addEventListener('input', apply);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    input.value = '';
                    apply();
                    input.blur();
                }
            });
        }

        function toggleChip(btn, key) {
            if (!btn) return;
            btn.addEventListener('click', function () {
                chipState[key] = !chipState[key];
                btn.classList.toggle('active', chipState[key]);
                apply();
            });
        }
        toggleChip(chipImportant, 'important');
        toggleChip(chipRepeat, 'repeat');

        if (expandBtn) {
            expandBtn.addEventListener('click', function () {
                document.querySelectorAll('[data-sc-tpl-stage]').forEach(function (stage) {
                    stage.open = true;
                });
            });
        }
        if (collapseBtn) {
            collapseBtn.addEventListener('click', function () {
                document.querySelectorAll('[data-sc-tpl-stage]').forEach(function (stage) {
                    stage.open = false;
                });
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
            if (!input) return;
            e.preventDefault();
            input.focus();
            input.select();
        });

        apply();
    })();

    // —— Template editor: подзадачи без перезагрузки ——
    (function initTemplateSubtasks() {
        var page = document.querySelector('[data-sc-hub="templates"]');
        if (!page || page.getAttribute('data-sc-hub') !== 'templates') return;
        var csrf = page.getAttribute('data-csrf') ||
            (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var deleteConfirm = page.getAttribute('data-i18n-delete-subtask') || 'Delete?';

        function postJson(url, payload) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload || {}),
            }).then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok && data && data.ok, data: data };
                });
            });
        }

        page.addEventListener('click', function (e) {
            var addBtn = e.target.closest('[data-sc-tpl-subtask-add]');
            if (addBtn) {
                e.preventDefault();
                var wrap = addBtn.closest('[data-sc-tpl-subtasks]');
                var input = wrap ? wrap.querySelector('[data-sc-tpl-subtask-title]') : null;
                var list = wrap ? wrap.querySelector('[data-sc-tpl-subtasks-list]') : null;
                var title = input ? String(input.value || '').trim() : '';
                var url = addBtn.getAttribute('data-url');
                if (!url || !title) return;
                addBtn.disabled = true;
                postJson(url, { title: title })
                    .then(function (result) {
                        addBtn.disabled = false;
                        if (!result.ok) {
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        var task = result.data.task;
                        var li = document.createElement('li');
                        li.setAttribute('data-sc-tpl-subtask', '');
                        li.setAttribute('data-id', String(task.id));
                        li.innerHTML = '<span class="cabinet-sc-tpl-subtasks__dot" aria-hidden="true"></span><span class="cabinet-sc-tpl-subtasks__title"></span><button type="button" class="cabinet-sc-tpl-subtasks__remove" data-sc-tpl-subtask-delete title="×" aria-label="×">×</button>';
                        li.querySelector('.cabinet-sc-tpl-subtasks__title').textContent = task.title;
                        li.querySelector('[data-sc-tpl-subtask-delete]').setAttribute('data-url', task.delete_url);
                        list.appendChild(li);
                        input.value = '';
                        input.focus();
                    })
                    .catch(function () {
                        addBtn.disabled = false;
                    });
                return;
            }

            var delBtn = e.target.closest('[data-sc-tpl-subtask-delete]');
            if (delBtn) {
                e.preventDefault();
                var url = delBtn.getAttribute('data-url');
                var li = delBtn.closest('[data-sc-tpl-subtask]');
                if (!url || !li) return;
                if (!window.confirm(deleteConfirm)) return;
                delBtn.disabled = true;
                postJson(url, {})
                    .then(function (result) {
                        if (!result.ok) {
                            delBtn.disabled = false;
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        if (li.parentNode) li.parentNode.removeChild(li);
                    })
                    .catch(function () {
                        delBtn.disabled = false;
                    });
            }
        });

        page.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            var input = e.target.closest('[data-sc-tpl-subtask-title]');
            if (!input) return;
            e.preventDefault();
            var wrap = input.closest('[data-sc-tpl-subtasks]');
            var btn = wrap ? wrap.querySelector('[data-sc-tpl-subtask-add]') : null;
            if (btn) btn.click();
        });
    })();

    // —— Create template: клон требует source ——
    (function initCreateTemplatePresets() {
        var form = document.querySelector('.cabinet-sc-create-tpl');
        if (!form) return;
        var source = form.querySelector('[data-sc-clone-source]');
        form.addEventListener('submit', function (e) {
            var preset = form.querySelector('input[name="preset"]:checked');
            if (!preset || preset.value !== 'clone') return;
            if (!source || !source.value) {
                e.preventDefault();
                alert('Выберите шаблон для клонирования');
                if (source) source.focus();
            }
        });
        if (source) {
            source.addEventListener('focus', function () {
                var cloneRadio = form.querySelector('input[name="preset"][value="clone"]');
                if (cloneRadio) cloneRadio.checked = true;
            });
        }
    })();

    // —— Template stages: rename / reorder / delete ——
    (function initTemplateStages() {
        var page = document.querySelector('[data-sc-hub="templates"]');
        if (!page || !page.querySelector('[data-sc-tpl-stages]')) return;

        var moveTpl = page.getAttribute('data-tpl-stage-move-url-template') || '';
        var updateTpl = page.getAttribute('data-tpl-stage-update-url-template') || '';
        var deleteTpl = page.getAttribute('data-tpl-stage-delete-url-template') || '';
        var csrf = page.getAttribute('data-csrf') ||
            (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var i18nDelete = page.getAttribute('data-i18n-delete-stage') || 'Delete?';
        var i18nHasTasks = page.getAttribute('data-i18n-stage-has-tasks') || '';

        function stagesRoot() {
            return page.querySelector('[data-sc-tpl-stages]');
        }

        function stageNodes() {
            var root = stagesRoot();
            if (!root) return [];
            return Array.prototype.filter.call(root.children, function (el) {
                return el.hasAttribute('data-sc-tpl-stage');
            });
        }

        function refreshStageMoveButtons() {
            var items = stageNodes();
            items.forEach(function (stage, idx) {
                var up = stage.querySelector('[data-sc-tpl-stage-move="up"]');
                var down = stage.querySelector('[data-sc-tpl-stage-move="down"]');
                if (up) up.disabled = idx === 0;
                if (down) down.disabled = idx === items.length - 1;
            });
        }

        function postJson(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body || {}),
            }).then(function (r) {
                return r.text().then(function (text) {
                    var data = null;
                    try {
                        data = text ? JSON.parse(text) : null;
                    } catch (err) {
                        data = null;
                    }
                    return { ok: r.ok && data && data.ok, data: data, status: r.status };
                });
            });
        }

        function saveStage(stage) {
            if (!updateTpl) return;
            var key = stage.getAttribute('data-stage-key');
            if (!key) return;
            var titleInput = stage.querySelector('[data-sc-tpl-stage-title]');
            var leadInput = stage.querySelector('[data-sc-tpl-stage-lead]');
            if (!titleInput) return;
            var title = String(titleInput.value || '').trim();
            if (!title) {
                titleInput.focus();
                return;
            }
            var url = updateTpl.replace('__KEY__', encodeURIComponent(key));
            stage.classList.add('is-busy');
            postJson(url, {
                title: title,
                lead: leadInput ? String(leadInput.value || '').trim() : '',
            }).then(function (result) {
                stage.classList.remove('is-busy');
                if (!result.ok) {
                    alert((result.data && result.data.message) || 'Error');
                }
            }).catch(function () {
                stage.classList.remove('is-busy');
            });
        }

        // capture: контролы внутри <summary>, иначе клик глохнет / только раскрывает этап
        page.addEventListener('click', function (e) {
            var controls = e.target.closest('[data-sc-tpl-stage-controls]');
            if (controls) {
                e.stopPropagation();
                if (!e.target.closest('input, textarea, select, a')) {
                    e.preventDefault();
                }
            }

            var moveBtn = e.target.closest('[data-sc-tpl-stage-move]');
            if (moveBtn) {
                e.preventDefault();
                e.stopPropagation();
                if (moveBtn.disabled || !moveTpl) return;

                var stage = moveBtn.closest('[data-sc-tpl-stage]');
                if (!stage) return;
                var key = stage.getAttribute('data-stage-key');
                var direction = moveBtn.getAttribute('data-sc-tpl-stage-move');
                var items = stageNodes();
                var idx = items.indexOf(stage);
                var swapWith = direction === 'up' ? idx - 1 : idx + 1;
                if (!key || swapWith < 0 || swapWith >= items.length) return;

                var url = moveTpl.replace('__KEY__', encodeURIComponent(key));
                moveBtn.disabled = true;
                stage.classList.add('is-busy');
                postJson(url, { direction: direction }).then(function (result) {
                    stage.classList.remove('is-busy');
                    if (!result.ok) {
                        refreshStageMoveButtons();
                        alert((result.data && result.data.message) || ('Error ' + (result.status || '')));
                        return;
                    }
                    var other = items[swapWith];
                    var root = stagesRoot();
                    if (direction === 'up') {
                        root.insertBefore(stage, other);
                    } else {
                        root.insertBefore(other, stage);
                    }
                    refreshStageMoveButtons();
                }).catch(function () {
                    stage.classList.remove('is-busy');
                    refreshStageMoveButtons();
                    alert('Error');
                });
                return;
            }

            var delBtn = e.target.closest('[data-sc-tpl-stage-delete]');
            if (delBtn) {
                e.preventDefault();
                e.stopPropagation();
                if (delBtn.disabled) {
                    if (i18nHasTasks) alert(i18nHasTasks);
                    return;
                }
                var stageDel = delBtn.closest('[data-sc-tpl-stage]');
                if (!stageDel || !deleteTpl) return;
                var delKey = stageDel.getAttribute('data-stage-key');
                if (!delKey) return;
                if (!window.confirm(i18nDelete)) return;
                var delUrl = deleteTpl.replace('__KEY__', encodeURIComponent(delKey));
                stageDel.classList.add('is-busy');
                postJson(delUrl, {}).then(function (result) {
                    stageDel.classList.remove('is-busy');
                    if (!result.ok) {
                        alert((result.data && result.data.message) || 'Error');
                        return;
                    }
                    stageDel.parentNode.removeChild(stageDel);
                    refreshStageMoveButtons();
                }).catch(function () {
                    stageDel.classList.remove('is-busy');
                    alert('Error');
                });
            }
        }, true);

        // не давать <summary> перехватить клик по ↑↓ / удалить
        page.addEventListener('mousedown', function (e) {
            if (e.target.closest('[data-sc-tpl-stage-move], [data-sc-tpl-stage-delete]')) {
                e.preventDefault();
            }
        }, true);

        page.addEventListener('change', function (e) {
            var input = e.target.closest('[data-sc-tpl-stage-title], [data-sc-tpl-stage-lead]');
            if (!input) return;
            var stage = input.closest('[data-sc-tpl-stage]');
            if (stage) saveStage(stage);
        });

        page.addEventListener('keydown', function (e) {
            var input = e.target.closest('[data-sc-tpl-stage-title], [data-sc-tpl-stage-lead]');
            if (!input) return;
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            }
        });

        refreshStageMoveButtons();
    })();

    // —— Template editor: ↑↓ без перезагрузки ——
    (function initTemplateTaskMove() {
        var page = document.querySelector('[data-sc-hub="templates"]');
        if (!page) return;
        var moveTpl = page.getAttribute('data-tpl-move-url-template') || '';
        var csrf = page.getAttribute('data-csrf') ||
            (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        if (!moveTpl) return;

        function taskLis(stage) {
            var ul = stage.querySelector('.cabinet-sc-tasks');
            if (!ul) return [];
            return Array.prototype.filter.call(ul.children, function (el) {
                return el.hasAttribute('data-sc-tpl-task');
            });
        }

        function refreshMoveButtons(stage) {
            var items = taskLis(stage);
            items.forEach(function (li, idx) {
                var up = li.querySelector('[data-sc-tpl-move="up"]');
                var down = li.querySelector('[data-sc-tpl-move="down"]');
                if (up) up.disabled = idx === 0;
                if (down) down.disabled = idx === items.length - 1;
            });
        }

        function postMove(url, direction) {
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ direction: direction }),
            }).then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok && data && data.ok, data: data };
                });
            });
        }

        page.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-sc-tpl-move]');
            if (!btn || btn.disabled) return;
            var li = btn.closest('[data-sc-tpl-task]');
            var stage = btn.closest('[data-sc-tpl-stage]');
            if (!li || !stage) return;

            var direction = btn.getAttribute('data-sc-tpl-move');
            var id = String(li.id || '').replace('tpl-task-', '');
            if (!id) return;

            var items = taskLis(stage);
            var idx = items.indexOf(li);
            var swapWith = direction === 'up' ? idx - 1 : idx + 1;
            if (swapWith < 0 || swapWith >= items.length) return;

            var url = moveTpl.replace('__ID__', id);
            btn.disabled = true;
            li.classList.add('is-busy');

            postMove(url, direction)
                .then(function (result) {
                    li.classList.remove('is-busy');
                    if (!result.ok) {
                        refreshMoveButtons(stage);
                        alert((result.data && result.data.message) || 'Error');
                        return;
                    }
                    var other = items[swapWith];
                    var list = li.parentNode;
                    if (direction === 'up') {
                        list.insertBefore(li, other);
                    } else {
                        list.insertBefore(other, li);
                    }
                    refreshMoveButtons(stage);
                    try {
                        li.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    } catch (err) { /* ignore */ }
                })
                .catch(function () {
                    li.classList.remove('is-busy');
                    refreshMoveButtons(stage);
                });
        });
    })();

    // —— Chronicle: «Ознакомлен» / «Вернуть в непрочитанные» без перезагрузки ——
    (function initChronicleMarkRead() {
        var page = document.querySelector('[data-sc-hub="chronicle"]');
        if (!page) return;

        var csrf = page.getAttribute('data-csrf') ||
            (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var markUrl = page.getAttribute('data-mark-read-url') || '';
        var unmarkUrl = page.getAttribute('data-mark-unread-url') || '';
        var markAllLabel = page.getAttribute('data-i18n-mark-all') || 'Mark all';
        var markedMsg = page.getAttribute('data-i18n-marked') || 'Notes marked as read';
        var unmarkedMsg = page.getAttribute('data-i18n-unmarked') || 'Notes marked as unread';
        if (!markUrl && !unmarkUrl) return;

        function postForm(url, form) {
            var body = new FormData(form);
            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body,
            }).then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok && data && data.ok !== false, data: data || {}, status: r.status };
                });
            });
        }

        function setCount(el, n) {
            if (!el) return;
            var count = Math.max(0, parseInt(n, 10) || 0);
            if (count < 1) {
                el.hidden = true;
                el.textContent = '0';
                return;
            }
            el.hidden = false;
            el.textContent = count > 99 ? '99+' : String(count);
        }

        function showFlash(message) {
            var slot = page.querySelector('[data-sc-flash-slot]');
            if (!slot) return;
            slot.innerHTML = '<div class="alert alert-success py-2 px-3 small">' +
                String(message || '').replace(/</g, '&lt;') + '</div>';
        }

        function syncCounts(unreadCount) {
            var count = Math.max(0, parseInt(unreadCount, 10) || 0);
            var list = page.querySelector('[data-sc-unread-list]');
            var remaining = list ? list.querySelectorAll('[data-sc-unread-item]').length : count;
            var section = page.querySelector('[data-sc-unread-section]');
            var empty = page.querySelector('[data-sc-unread-empty]');
            var preset = page.getAttribute('data-filter-preset') || 'unread';

            if (count === 0 || remaining === 0) {
                if (section) section.hidden = true;
                page.querySelectorAll('[data-sc-mark-all-wrap]').forEach(function (wrap) {
                    wrap.hidden = true;
                });
                if (empty && preset === 'unread') empty.hidden = false;
            } else {
                if (section) section.hidden = false;
                if (empty) empty.hidden = true;
                page.querySelectorAll('[data-sc-mark-all-wrap]').forEach(function (wrap) {
                    wrap.hidden = false;
                });
            }

            setCount(page.querySelector('[data-sc-unread-section-count]'), remaining || count);
            setCount(page.querySelector('[data-sc-unread-preset-count]'), count);
            setCount(document.querySelector('[data-sc-unread-nav-count]'), count);
            setCount(document.querySelector('[data-sc-unread-header-count]'), count);

            page.querySelectorAll('[data-sc-mark-all-label]').forEach(function (label) {
                label.textContent = count > 0 ? (markAllLabel + ' (' + count + ')') : markAllLabel;
            });
        }

        function setFeedNoteReadState(noteId, isRead) {
            if (!noteId) return;
            page.querySelectorAll('[data-sc-feed-note][data-note-id="' + noteId + '"]').forEach(function (item) {
                item.setAttribute('data-note-read', isRead ? '1' : '0');
                item.classList.toggle('is-unread', !isRead);
                var actions = item.querySelector('[data-sc-note-actions]');
                if (!actions) return;
                var readForm = actions.querySelector('[data-sc-mark-read]');
                var unreadForm = actions.querySelector('[data-sc-mark-unread]');
                if (readForm) readForm.hidden = !!isRead;
                if (unreadForm) unreadForm.hidden = !isRead;
                actions.querySelectorAll('button[type="submit"]').forEach(function (b) {
                    b.disabled = false;
                });
            });
        }

        function updateAfterRead(unreadCount, removedIds, markAll) {
            var list = page.querySelector('[data-sc-unread-list]');
            if (markAll) {
                if (list) list.innerHTML = '';
                page.querySelectorAll('[data-sc-feed-note]').forEach(function (item) {
                    var id = item.getAttribute('data-note-id');
                    if (id) setFeedNoteReadState(id, true);
                });
            } else if (removedIds && removedIds.length) {
                removedIds.forEach(function (id) {
                    if (list) {
                        var item = list.querySelector('[data-sc-unread-item][data-note-id="' + id + '"]');
                        if (item && item.parentNode) item.parentNode.removeChild(item);
                    }
                    setFeedNoteReadState(id, true);
                });
            }
            syncCounts(unreadCount);
        }

        function updateAfterUnread(unreadCount, noteIds) {
            (noteIds || []).forEach(function (id) {
                setFeedNoteReadState(id, false);
            });
            syncCounts(unreadCount);
        }

        page.addEventListener('submit', function (e) {
            var readForm = e.target.closest('[data-sc-mark-read]');
            var unreadForm = e.target.closest('[data-sc-mark-unread]');
            if (!readForm && !unreadForm) return;
            var form = readForm || unreadForm;
            if (!page.contains(form)) return;
            e.preventDefault();

            var btn = form.querySelector('button[type="submit"]');
            var noteId = form.getAttribute('data-note-id');
            var noteIds = noteId ? [String(noteId)] : [];
            if (!noteId) {
                form.querySelectorAll('input[name="note_ids[]"]').forEach(function (input) {
                    if (input.value) noteIds.push(String(input.value));
                });
            }

            if (btn) btn.disabled = true;

            if (unreadForm) {
                if (!unmarkUrl) {
                    if (btn) btn.disabled = false;
                    return;
                }
                postForm(unmarkUrl, form)
                    .then(function (result) {
                        if (!result.ok) {
                            if (btn) btn.disabled = false;
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        updateAfterUnread(result.data.unread_count, noteIds);
                        showFlash(result.data.message || unmarkedMsg);
                    })
                    .catch(function () {
                        if (btn) btn.disabled = false;
                        alert('Error');
                    });
                return;
            }

            var markAll = form.getAttribute('data-sc-mark-all') === '1';
            postForm(markUrl, form)
                .then(function (result) {
                    if (!result.ok) {
                        if (btn) btn.disabled = false;
                        alert((result.data && result.data.message) || 'Error');
                        return;
                    }
                    updateAfterRead(result.data.unread_count, noteIds, markAll);
                    showFlash(result.data.message || markedMsg);
                })
                .catch(function () {
                    if (btn) btn.disabled = false;
                    alert('Error');
                });
        });
    })();

    // —— Delete project: Bootstrap modal instead of window.confirm ——
    (function initDeleteProjectModal() {
        var modalEl = document.getElementById('cabinetScDeleteProjectModal');
        if (!modalEl) return;

        var form = modalEl.querySelector('[data-sc-delete-project-form]');
        var domainEl = modalEl.querySelector('[data-sc-delete-project-domain]');
        var submitBtn = modalEl.querySelector('[data-sc-delete-project-submit]');
        var triggers = document.querySelectorAll('[data-sc-delete-project]');
        if (!form || !triggers.length) return;

        function openModal(url, domain) {
            form.setAttribute('action', url || '');
            if (domainEl) {
                domainEl.textContent = domain ? String(domain) : '';
            }
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else if (window.jQuery) {
                window.jQuery(modalEl).modal('show');
            }
        }

        triggers.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openModal(btn.getAttribute('data-url'), btn.getAttribute('data-domain'));
            });
        });

        form.addEventListener('submit', function () {
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        });
    })();
})();
