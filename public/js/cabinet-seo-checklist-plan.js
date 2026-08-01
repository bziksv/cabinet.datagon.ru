(function () {
    var root = document.getElementById('cabinetSeoChecklistPlan');
    if (!root) return;

    var csrf = root.getAttribute('data-csrf') || '';
    var statusTpl = root.getAttribute('data-status-url-template') || '';
    var timerStartTpl = root.getAttribute('data-timer-start-url-template') || '';
    var timerStopTpl = root.getAttribute('data-timer-stop-url-template') || '';
    var labelStart = root.getAttribute('data-i18n-timer-start') || 'Start timer';
    var labelStop = root.getAttribute('data-i18n-timer-stop') || 'Stop timer';
    var labelStartShort = root.getAttribute('data-i18n-timer-start-short') || 'Start';
    var labelStopShort = root.getAttribute('data-i18n-timer-stop-short') || 'Stop';
    var commentRequired = root.getAttribute('data-i18n-comment-required') || 'Comment required';
    var chooseStatusLabel = root.getAttribute('data-i18n-choose-status') || 'Choose task status';

    function formatDuration(total) {
        if (window.cabinetSeoChecklistFormatDuration) {
            return window.cabinetSeoChecklistFormatDuration(total);
        }
        total = Math.max(0, Math.floor(total || 0));
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        if (h > 0) {
            return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
        return m + ':' + String(s).padStart(2, '0');
    }

    function parseStartedAt(iso) {
        if (!iso) return null;
        var t = Date.parse(iso);
        return Number.isFinite(t) ? t : null;
    }

    function urlFor(tpl, projectId, itemId) {
        return String(tpl || '')
            .replace('__PROJECT__', String(projectId))
            .replace('__ID__', String(itemId));
    }

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
            return r.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                return { ok: r.ok && data && data.ok, data: data, status: r.status };
            });
        });
    }

    function itemDisplaySeconds(itemEl) {
        var base = parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0;
        if (itemEl.getAttribute('data-timer-running') !== '1') return base;
        var started = parseStartedAt(itemEl.getAttribute('data-timer-started-at'));
        if (!started) return base;
        return base + Math.max(0, Math.floor((Date.now() - started) / 1000));
    }

    function applyTimerUi(itemEl, state) {
        if (!itemEl || !state) return;
        if (typeof state.time_spent_seconds === 'number') {
            itemEl.setAttribute('data-time-spent', String(state.time_spent_seconds));
        }
        var running = !!state.timer_running;
        itemEl.setAttribute('data-timer-running', running ? '1' : '0');
        itemEl.setAttribute('data-timer-started-at', running && state.timer_started_at ? state.timer_started_at : '');
        itemEl.classList.toggle('is-timing', running);

        var timeEl = itemEl.querySelector('[data-sc-time]');
        if (timeEl) {
            timeEl.classList.toggle('is-running', running);
            timeEl.textContent = formatDuration(
                typeof state.display_seconds === 'number' ? state.display_seconds : itemDisplaySeconds(itemEl)
            );
        }

        var btn = itemEl.querySelector('[data-sc-timer]');
        if (btn) {
            btn.textContent = running ? labelStopShort : labelStartShort;
            btn.title = running ? labelStop : labelStart;
            btn.classList.toggle('btn-danger', running);
            btn.classList.toggle('btn-outline-success', !running);
        }
    }

    function syncFromActive(active) {
        root.querySelectorAll('[data-sc-plan-item]').forEach(function (itemEl) {
            var id = parseInt(itemEl.getAttribute('data-id') || '0', 10);
            if (active && id === active.item_id) {
                applyTimerUi(itemEl, {
                    time_spent_seconds: active.time_spent_seconds,
                    display_seconds: active.display_seconds,
                    timer_running: true,
                    timer_started_at: active.started_at,
                });
            } else if (itemEl.getAttribute('data-timer-running') === '1') {
                applyTimerUi(itemEl, {
                    time_spent_seconds: parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0,
                    display_seconds: parseInt(itemEl.getAttribute('data-time-spent') || '0', 10) || 0,
                    timer_running: false,
                    timer_started_at: null,
                });
            }
        });
    }

    function refreshGroupCounts() {
        root.querySelectorAll('[data-sc-plan-group]').forEach(function (group) {
            var n = group.querySelectorAll('[data-sc-plan-item]').length;
            var countEl = group.querySelector('[data-sc-plan-count]');
            if (countEl) countEl.textContent = String(n);
            group.style.display = n === 0 ? 'none' : '';
        });
    }

    function removeItem(itemEl) {
        var group = itemEl.closest('[data-sc-plan-group]');
        itemEl.parentNode.removeChild(itemEl);
        if (group) refreshGroupCounts();
    }

    function applyStatusUi(itemEl, status) {
        if (!status) return;
        itemEl.setAttribute('data-status', status);
        itemEl.classList.toggle('is-doing', status === 'doing' || status === 'rework');
        var select = itemEl.querySelector('[data-sc-status]');
        if (select && select.value !== status) select.value = status;
    }

    function askStatusAfterStop(itemEl) {
        var select = itemEl.querySelector('[data-sc-status]');
        if (!select) return;
        var canApprove = (itemEl.getAttribute('data-can-approve') || root.getAttribute('data-can-approve')) === '1';
        var options = [];
        var defaultValue = 'rework';
        var hasRework = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.disabled || !opt.value || opt.value === 'todo') return;
            if ((opt.value === 'done' || opt.value === 'skip') && !canApprove) return;
            options.push({ value: opt.value, label: String(opt.textContent || '').trim() });
            if (opt.value === 'rework') hasRework = true;
        });
        if (!options.length) return;
        if (!hasRework) defaultValue = options[0].value;

        if (typeof window.cabinetSeoChecklistAskStatus === 'function') {
            window.cabinetSeoChecklistAskStatus({
                options: options,
                defaultValue: defaultValue,
                onSelect: function (value, note) {
                    setStatus(itemEl, value, note);
                },
            });
            return;
        }
        setStatus(itemEl, defaultValue);
    }

    function toggleTimer(itemEl) {
        var id = itemEl.getAttribute('data-id');
        var projectId = itemEl.getAttribute('data-project-id');
        var running = itemEl.getAttribute('data-timer-running') === '1';
        var url = urlFor(running ? timerStopTpl : timerStartTpl, projectId, id);
        itemEl.classList.add('is-busy');
        postJson(url, {}).then(function (result) {
            itemEl.classList.remove('is-busy');
            if (!result.ok) {
                alert((result.data && result.data.message) || 'Error');
                return;
            }
            if (result.data.stopped_item) {
                var prev = root.querySelector('[data-sc-plan-item][data-id="' + result.data.stopped_item.id + '"]');
                if (prev) applyTimerUi(prev, result.data.stopped_item);
            }
            if (result.data.item) {
                applyTimerUi(itemEl, result.data.item);
                if (result.data.item.status) applyStatusUi(itemEl, result.data.item.status);
            }
            if (Object.prototype.hasOwnProperty.call(result.data, 'active')) {
                if (result.data.active && window.cabinetSeoChecklistUpsertHeaderTimer) {
                    window.cabinetSeoChecklistUpsertHeaderTimer(result.data.active);
                } else if (!result.data.active && window.cabinetSeoChecklistRemoveHeaderTimer) {
                    window.cabinetSeoChecklistRemoveHeaderTimer();
                }
                syncFromActive(result.data.active || null);
            }
            if (running) {
                askStatusAfterStop(itemEl);
            }
        }).catch(function () {
            itemEl.classList.remove('is-busy');
            alert('Error');
        });
    }

    function setStatus(itemEl, status, noteFromModal) {
        var id = itemEl.getAttribute('data-id');
        var projectId = itemEl.getAttribute('data-project-id');
        var payload = { status: status };
        if (status === 'skip' || status === 'blocked' || status === 'clarify') {
            var note = noteFromModal;
            if (note === undefined) {
                note = window.prompt(commentRequired);
                if (note === null) {
                    var select = itemEl.querySelector('[data-sc-status]');
                    if (select) select.value = itemEl.getAttribute('data-status') || 'todo';
                    return;
                }
            }
            note = String(note || '').trim();
            if (!note) {
                var selectEmpty = itemEl.querySelector('[data-sc-status]');
                if (selectEmpty) selectEmpty.value = itemEl.getAttribute('data-status') || 'todo';
                return;
            }
            payload.note = note;
        }

        itemEl.classList.add('is-busy');
        postJson(urlFor(statusTpl, projectId, id), payload).then(function (result) {
            itemEl.classList.remove('is-busy');
            if (!result.ok) {
                var select = itemEl.querySelector('[data-sc-status]');
                if (select) select.value = itemEl.getAttribute('data-status') || 'todo';
                alert((result.data && result.data.message) || 'Error');
                return;
            }
            var next = result.data.item.status;
            applyStatusUi(itemEl, next);
            if (result.data.item.time_spent_seconds !== undefined || result.data.item.timer_running !== undefined) {
                applyTimerUi(itemEl, result.data.item);
            }
            if (Object.prototype.hasOwnProperty.call(result.data, 'active')) {
                if (result.data.active && window.cabinetSeoChecklistUpsertHeaderTimer) {
                    window.cabinetSeoChecklistUpsertHeaderTimer(result.data.active);
                } else if (!result.data.active && window.cabinetSeoChecklistRemoveHeaderTimer) {
                    window.cabinetSeoChecklistRemoveHeaderTimer();
                }
                syncFromActive(result.data.active || null);
            }
            // закрытые задачи убираем из плана
            if (next === 'done' || next === 'skip') {
                removeItem(itemEl);
            }
        }).catch(function () {
            itemEl.classList.remove('is-busy');
            var select = itemEl.querySelector('[data-sc-status]');
            if (select) select.value = itemEl.getAttribute('data-status') || 'todo';
            alert('Error');
        });
    }

    root.addEventListener('click', function (e) {
        var timerBtn = e.target.closest('[data-sc-timer]');
        if (timerBtn) {
            e.preventDefault();
            var item = timerBtn.closest('[data-sc-plan-item]');
            if (item && !item.classList.contains('is-busy')) toggleTimer(item);
        }
    });

    root.addEventListener('change', function (e) {
        var select = e.target.closest('[data-sc-status]');
        if (select) {
            var item = select.closest('[data-sc-plan-item]');
            if (!item || item.classList.contains('is-busy')) return;
            setStatus(item, select.value);
            return;
        }

        var subDone = e.target.closest('[data-sc-plan-sub-done]');
        if (!subDone) return;
        var sub = subDone.closest('[data-sc-plan-sub]');
        if (!sub || sub.classList.contains('is-busy')) return;
        var subId = sub.getAttribute('data-id');
        var projectId = sub.getAttribute('data-project-id');
        var next = subDone.checked ? 'done' : 'todo';
        var prev = sub.getAttribute('data-status') || 'todo';
        sub.classList.add('is-busy');
        postJson(urlFor(statusTpl, projectId, subId), { status: next }).then(function (result) {
            sub.classList.remove('is-busy');
            if (!result.ok) {
                subDone.checked = prev === 'done' || prev === 'skip';
                alert((result.data && result.data.message) || 'Error');
                return;
            }
            var status = result.data.item.status;
            sub.setAttribute('data-status', status);
            sub.classList.toggle('is-done', status === 'done' || status === 'skip');
            var head = sub.closest('.cabinet-sc-plan__subs');
            if (head) {
                var all = head.querySelectorAll('[data-sc-plan-sub]').length;
                var open = head.querySelectorAll('[data-sc-plan-sub]:not(.is-done)').length;
                var meta = head.querySelector('.cabinet-sc-plan__subs-head span');
                if (meta) meta.textContent = open + '/' + all;
            }
        }).catch(function () {
            sub.classList.remove('is-busy');
            subDone.checked = prev === 'done' || prev === 'skip';
            alert('Error');
        });
    });

    window.setInterval(function () {
        root.querySelectorAll('[data-sc-plan-item][data-timer-running="1"]').forEach(function (itemEl) {
            var timeEl = itemEl.querySelector('[data-sc-time]');
            if (timeEl) timeEl.textContent = formatDuration(itemDisplaySeconds(itemEl));
        });
    }, 1000);
})();
