{{-- Мастер: подключить доски SEO-чеклиста команды к Site Audit (проекты аудита + автозадачи + авторасписание) --}}
@php
    $bulkTeams = $checklistTeams ?? collect();
@endphp
<div class="modal fade" id="sa-checklist-bulk-modal" tabindex="-1" aria-labelledby="sa-checklist-bulk-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form method="post" action="{{ route('pages.site-audit.checklist-bulk') }}" id="sa-bulk-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="sa-checklist-bulk-modal-title">Подключить команду к аудиту</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3">
                        Для каждой доски выбранной команды найдём или создадим проект аудита того же сайта,
                        подключим к нему автозадачи в чеклист и поставим еженедельное авторасписание.
                        Расписания разнесём по дням недели и ночным часам (02:00–05:00), лимит тарифа соблюдается.
                    </p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-7">
                            <label class="form-label fw-medium mb-1" for="sa-bulk-team">Команда</label>
                            <select name="team_id" id="sa-bulk-team" class="form-select">
                                <option value="">— выберите команду —</option>
                                @foreach($bulkTeams as $bt)
                                    <option value="{{ $bt->id }}">{{ $bt->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <button type="button" class="btn btn-outline-primary w-100" id="sa-bulk-preview" disabled>
                                Показать план
                            </button>
                        </div>
                    </div>

                    <div id="sa-bulk-plan" aria-live="polite"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="sa-bulk-apply" disabled>Подключить выбранные</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('sa-checklist-bulk-modal');
    if (!modal) return;

    var form = document.getElementById('sa-bulk-form');
    var teamSel = document.getElementById('sa-bulk-team');
    var previewBtn = document.getElementById('sa-bulk-preview');
    var applyBtn = document.getElementById('sa-bulk-apply');
    var planBox = document.getElementById('sa-bulk-plan');
    var previewUrl = @json(route('pages.site-audit.checklist-bulk.preview'));
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
    var labels = {
        ok: 'Подключим',
        no_rights: 'Нет прав',
        duplicate: 'Дубликат сайта',
        no_domain: 'Нет сайта'
    };

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = text;
        return n;
    }

    function updateApply() {
        var boxes = planBox.querySelectorAll('input[name="board_ids[]"]');
        var checked = planBox.querySelectorAll('input[name="board_ids[]"]:checked');
        applyBtn.disabled = boxes.length === 0 || checked.length === 0;
    }

    function resetPlan(message) {
        planBox.textContent = '';
        if (message) planBox.appendChild(el('div', 'text-secondary small', message));
        applyBtn.disabled = true;
    }

    function renderPlan(data) {
        planBox.textContent = '';
        if (!data || !data.ok) {
            var err = el('div', 'alert alert-warning mb-0', (data && data.error) || 'Не удалось построить план');
            planBox.appendChild(err);
            applyBtn.disabled = true;
            return;
        }
        if (!data.rows.length) {
            planBox.appendChild(el('div', 'alert alert-info mb-0', 'В этой команде пока нет активных досок чеклиста.'));
            applyBtn.disabled = true;
            return;
        }

        var s = data.summary || {};
        var summary = 'Досок: ' + s.boards + ' · к подключению: ' + s.selected +
            ' · новых расписаний: ' + s.new_schedules +
            ' · уже с расписанием: ' + s.has_schedule +
            ' · без расписания (лимит): ' + s.no_schedule +
            ' · свободных слотов после: ' + s.free_slots_left;
        planBox.appendChild(el('div', 'small fw-medium mb-2', summary));

        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm align-middle mb-0');
        var thead = el('thead');
        var hr = el('tr');
        ['', 'Сайт', 'Доска', 'Проект аудита', 'Авторасписание', 'Комментарий'].forEach(function (t) {
            hr.appendChild(el('th', 'small text-secondary fw-normal', t));
        });
        thead.appendChild(hr);
        table.appendChild(thead);

        var tbody = el('tbody');
        data.rows.forEach(function (row) {
            var tr = el('tr', row.status === 'ok' ? '' : 'text-secondary');

            var tdCb = el('td');
            var cb = el('input', 'form-check-input');
            cb.type = 'checkbox';
            cb.name = 'board_ids[]';
            cb.value = String(row.board_id);
            cb.checked = !!row.selected;
            cb.disabled = row.status !== 'ok';
            cb.addEventListener('change', updateApply);
            tdCb.appendChild(cb);
            tr.appendChild(tdCb);

            tr.appendChild(el('td', 'small fw-medium', row.domain || '—'));
            tr.appendChild(el('td', 'small', row.board_title));

            var auditText = row.status !== 'ok' ? '—' : (row.audit_exists ? 'есть, подключим' : 'создадим');
            tr.appendChild(el('td', 'small', auditText));

            var schText;
            if (row.status !== 'ok') schText = '—';
            else if (row.has_schedule) schText = 'уже включено';
            else if (row.slot_label) schText = row.slot_label;
            else schText = 'без расписания';
            tr.appendChild(el('td', 'small', schText));

            var note = row.status !== 'ok'
                ? (labels[row.status] || row.status) + (row.note ? ' — ' + row.note : '')
                : (row.note || '');
            tr.appendChild(el('td', 'small', note));

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        planBox.appendChild(wrap);

        updateApply();
    }

    teamSel.addEventListener('change', function () {
        previewBtn.disabled = !teamSel.value;
        resetPlan(teamSel.value ? 'Нажмите «Показать план».' : '');
    });

    previewBtn.addEventListener('click', function () {
        if (!teamSel.value) return;
        previewBtn.disabled = true;
        resetPlan('Считаем план…');
        var body = new URLSearchParams();
        body.append('team_id', teamSel.value);
        body.append('_token', csrf);
        fetch(previewUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: body.toString()
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'Ошибка ответа сервера' }; });
        }).then(function (data) {
            renderPlan(data);
        }).catch(function () {
            renderPlan({ ok: false, error: 'Не удалось связаться с сервером' });
        }).finally(function () {
            previewBtn.disabled = !teamSel.value;
        });
    });

    form.addEventListener('submit', function (e) {
        if (!planBox.querySelector('input[name="board_ids[]"]:checked')) {
            e.preventDefault();
        }
    });

    modal.addEventListener('hidden.bs.modal', function () {
        teamSel.value = '';
        previewBtn.disabled = true;
        resetPlan('');
    });
})();
</script>
