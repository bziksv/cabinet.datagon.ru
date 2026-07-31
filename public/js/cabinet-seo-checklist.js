(function () {
    var root = document.getElementById('cabinetSeoChecklist');
    if (!root) return;

    var csrf = root.getAttribute('data-csrf') || '';
    var statusTpl = root.getAttribute('data-status-url-template') || '';
    var noteTpl = root.getAttribute('data-note-url-template') || '';
    var subtaskTpl = root.getAttribute('data-subtask-url-template') || '';
    var commentRequired = root.getAttribute('data-i18n-comment-required') || 'Comment required';

    function urlFor(tpl, id) {
        return String(tpl || '').replace('__ID__', String(id));
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
            return r.json().then(function (data) {
                return { ok: r.ok && data && data.ok, status: r.status, data: data };
            });
        });
    }

    function updateProgress(progress) {
        if (!progress) return;
        var label = root.querySelector('[data-sc-progress-label]');
        var pctEl = root.querySelector('[data-sc-progress-pct]');
        var bar = root.querySelector('[data-sc-progress-bar]');
        var pct = progress.total > 0 ? Math.round(100 * progress.done / progress.total) : 0;
        if (label) label.textContent = progress.done + '/' + progress.total;
        if (pctEl) pctEl.textContent = pct + '%';
        if (bar) bar.style.width = pct + '%';
    }

    function applyItemUi(itemEl, status) {
        itemEl.setAttribute('data-status', status);
        itemEl.classList.toggle('is-done', status === 'done' || status === 'skip');
        var checkbox = itemEl.querySelector('[data-sc-done]');
        if (checkbox) checkbox.checked = status === 'done' || status === 'skip';
        var select = itemEl.querySelector('[data-sc-status]');
        if (select && select.value !== status) select.value = status;
    }

    function setStatus(itemEl, status) {
        var id = itemEl.getAttribute('data-id');
        var payload = { status: status };
        if (status === 'skip' || status === 'blocked') {
            var note = window.prompt(commentRequired);
            if (note === null || String(note).trim() === '') {
                applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
                return;
            }
            payload.note = String(note).trim();
        }

        itemEl.classList.add('is-busy');
        postJson(urlFor(statusTpl, id), payload)
            .then(function (result) {
                itemEl.classList.remove('is-busy');
                if (!result.ok) {
                    applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                applyItemUi(itemEl, result.data.item.status);
                updateProgress(result.data.progress);
                applyFilter(currentFilter);
            })
            .catch(function () {
                itemEl.classList.remove('is-busy');
                applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');
            });
    }

    var currentFilter = 'all';

    function applyFilter(filter) {
        currentFilter = filter || 'all';
        root.querySelectorAll('[data-sc-filter]').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-sc-filter') === currentFilter);
        });
        root.querySelectorAll('[data-sc-item]').forEach(function (item) {
            var status = item.getAttribute('data-status') || 'todo';
            var important = item.getAttribute('data-important') === '1';
            var open = status === 'todo' || status === 'doing' || status === 'blocked';
            var show = currentFilter === 'all'
                || (currentFilter === 'open' && open)
                || (currentFilter === 'important' && important);
            item.classList.toggle('is-hidden-filter', !show);
        });
    }

    root.querySelectorAll('[data-sc-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyFilter(btn.getAttribute('data-sc-filter'));
        });
    });

    root.querySelectorAll('[data-sc-item]').forEach(function (itemEl) {
        applyItemUi(itemEl, itemEl.getAttribute('data-status') || 'todo');

        var checkbox = itemEl.querySelector('[data-sc-done]');
        if (checkbox) {
            checkbox.addEventListener('change', function () {
                setStatus(itemEl, checkbox.checked ? 'done' : 'todo');
            });
        }

        var select = itemEl.querySelector('[data-sc-status]');
        if (select) {
            select.addEventListener('change', function () {
                setStatus(itemEl, select.value);
            });
        }

        var toggleNotes = itemEl.querySelector('[data-sc-toggle-notes]');
        var notesBox = itemEl.querySelector('[data-sc-notes]');
        if (toggleNotes && notesBox) {
            toggleNotes.addEventListener('click', function () {
                notesBox.classList.toggle('d-none');
            });
        }

        var saveNote = itemEl.querySelector('[data-sc-note-save]');
        var noteBody = itemEl.querySelector('[data-sc-note-body]');
        var notesList = itemEl.querySelector('[data-sc-notes-list]');
        if (saveNote && noteBody && notesList) {
            saveNote.addEventListener('click', function () {
                var body = noteBody.value.trim();
                if (!body) return;
                saveNote.disabled = true;
                postJson(urlFor(noteTpl, itemEl.getAttribute('data-id')), { body: body })
                    .then(function (result) {
                        saveNote.disabled = false;
                        if (!result.ok) {
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        var li = document.createElement('li');
                        li.innerHTML = '<span class="text-secondary small">' + result.data.note.created_at + '</span> ' +
                            String(result.data.note.body).replace(/</g, '&lt;');
                        notesList.insertBefore(li, notesList.firstChild);
                        noteBody.value = '';
                    })
                    .catch(function () {
                        saveNote.disabled = false;
                    });
            });
        }

        var addSub = itemEl.querySelector('[data-sc-subtask-add]');
        var subTitle = itemEl.querySelector('[data-sc-subtask-title]');
        if (addSub && subTitle) {
            addSub.addEventListener('click', function () {
                var title = subTitle.value.trim();
                if (!title) return;
                addSub.disabled = true;
                postJson(urlFor(subtaskTpl, itemEl.getAttribute('data-id')), { title: title })
                    .then(function (result) {
                        addSub.disabled = false;
                        if (!result.ok) {
                            alert((result.data && result.data.message) || 'Error');
                            return;
                        }
                        var li = document.createElement('li');
                        li.className = 'small text-secondary';
                        li.textContent = '↳ ' + result.data.item.title;
                        if (notesList) notesList.appendChild(li);
                        subTitle.value = '';
                    })
                    .catch(function () {
                        addSub.disabled = false;
                    });
            });
        }
    });
})();
