{{-- Единый попап создания команды (профиль / чеклист / аудит): название + участники --}}
@php
    $returnTo = $returnTo ?? null;
    $modalId = $modalId ?? 'cabinet-team-create-modal';
    $showManageLink = isset($showManageLink) ? (bool) $showManageLink : ($returnTo !== 'profile');
    $lead = $lead ?? 'Команда общая для Чеклиста, SEO-отчётов и Аудита сайта.';
    $teamRoleLabels = $teamRoleLabels ?? \App\SeoChecklist\SeoChecklistTeam::roleLabels();
    $teamCandidates = $teamCandidates ?? collect();
    $inviteRoles = collect($teamRoleLabels)->except(['owner']);
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ route('pages.seo-checklist.teams.store') }}" data-team-create-form>
                @csrf
                @if($returnTo)
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                @endif
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-title">Новая команда</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3">{{ $lead }}</p>

                    <div class="mb-3">
                        <label class="form-label fw-medium" for="{{ $modalId }}-title-input">Название</label>
                        <input type="text"
                               name="title"
                               id="{{ $modalId }}-title-input"
                               class="form-control"
                               required
                               maxlength="120"
                               placeholder="Например: Команда клиента X"
                               autocomplete="off"
                               value="{{ old('title') }}">
                    </div>

                    <div class="cabinet-team-create-members">
                        <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                            <label class="form-label fw-medium mb-0">Участники</label>
                            <span class="small text-secondary">Вы уже Owner. Остальных добавьте здесь.</span>
                        </div>

                        <ul class="list-group list-group-flush border rounded mb-2 d-none" data-team-members-list></ul>
                        <p class="small text-secondary mb-2" data-team-members-empty>Пока никого в списке — добавьте участников ниже или создайте команду и дозаполните позже.</p>

                        <div class="row g-2 align-items-end" data-team-member-row>
                            <div class="col-md-4">
                                <label class="form-label small mb-1" for="{{ $modalId }}-user">Из известных</label>
                                <select id="{{ $modalId }}-user" class="form-select form-select-sm" data-team-member-user>
                                    <option value="">Выберите…</option>
                                    @foreach($teamCandidates as $cand)
                                        @php
                                            $candLabel = trim(($cand->name ?? '') . ' ' . ($cand->last_name ?? '')) ?: $cand->email;
                                        @endphp
                                        <option value="{{ $cand->id }}"
                                                data-email="{{ e($cand->email) }}"
                                                data-label="{{ e($candLabel) }}">
                                            {{ $candLabel }} · {{ $cand->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1" for="{{ $modalId }}-email">Или email</label>
                                <input type="email"
                                       id="{{ $modalId }}-email"
                                       class="form-control form-control-sm"
                                       placeholder="user@example.com"
                                       autocomplete="off"
                                       data-team-member-email>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1" for="{{ $modalId }}-role">Роль</label>
                                <select id="{{ $modalId }}-role" class="form-select form-select-sm" data-team-member-role>
                                    @foreach($inviteRoles as $rk => $rl)
                                        <option value="{{ $rk }}" @if($rk === 'participant') selected @endif>{{ $rl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" data-team-member-add>
                                    Добавить
                                </button>
                            </div>
                        </div>
                        <div class="form-text mt-2">
                            Участник должен уже быть зарегистрирован в кабинете (приглашение по email ищет существующий аккаунт).
                        </div>
                        <div data-team-members-hidden></div>
                    </div>
                </div>
                <div class="modal-footer{{ $showManageLink ? ' justify-content-between' : '' }}">
                    @if($showManageLink)
                        <a href="{{ route('profile.index') }}#team" class="btn btn-link btn-sm px-0">Управление командами</a>
                    @endif
                    <div class="d-flex gap-2 ms-auto">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Создать</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById(@json($modalId));
    if (!modal || modal.dataset.teamCreateBound) return;
    modal.dataset.teamCreateBound = '1';

    var form = modal.querySelector('[data-team-create-form]');
    var listEl = modal.querySelector('[data-team-members-list]');
    var emptyEl = modal.querySelector('[data-team-members-empty]');
    var hiddenEl = modal.querySelector('[data-team-members-hidden]');
    var userEl = modal.querySelector('[data-team-member-user]');
    var emailEl = modal.querySelector('[data-team-member-email]');
    var roleEl = modal.querySelector('[data-team-member-role]');
    var addBtn = modal.querySelector('[data-team-member-add]');
    var members = [];

    function roleLabel(role) {
        var opt = roleEl && roleEl.querySelector('option[value="' + role + '"]');
        return opt ? opt.textContent : role;
    }

    function syncEmpty() {
        if (!listEl || !emptyEl) return;
        var has = members.length > 0;
        listEl.classList.toggle('d-none', !has);
        emptyEl.classList.toggle('d-none', has);
    }

    function render() {
        if (!listEl || !hiddenEl) return;
        listEl.innerHTML = '';
        hiddenEl.innerHTML = '';
        members.forEach(function (m, idx) {
            var li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center justify-content-between gap-2 py-2 px-3';
            var left = document.createElement('div');
            left.className = 'small';
            left.innerHTML = '<strong></strong> <span class="text-secondary"></span>';
            left.querySelector('strong').textContent = m.label;
            left.querySelector('span').textContent = '· ' + roleLabel(m.role);
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-sm btn-outline-danger';
            rm.setAttribute('aria-label', 'Убрать');
            rm.textContent = '×';
            rm.addEventListener('click', function () {
                members.splice(idx, 1);
                render();
            });
            li.appendChild(left);
            li.appendChild(rm);
            listEl.appendChild(li);

            if (m.user_id) {
                var iu = document.createElement('input');
                iu.type = 'hidden';
                iu.name = 'members[' + idx + '][user_id]';
                iu.value = String(m.user_id);
                hiddenEl.appendChild(iu);
            }
            if (m.email) {
                var ie = document.createElement('input');
                ie.type = 'hidden';
                ie.name = 'members[' + idx + '][email]';
                ie.value = m.email;
                hiddenEl.appendChild(ie);
            }
            var ir = document.createElement('input');
            ir.type = 'hidden';
            ir.name = 'members[' + idx + '][role]';
            ir.value = m.role;
            hiddenEl.appendChild(ir);
        });
        syncEmpty();
    }

    function clearDraft() {
        if (userEl) userEl.value = '';
        if (emailEl) emailEl.value = '';
        if (roleEl) {
            var part = roleEl.querySelector('option[value="participant"]');
            roleEl.value = part ? 'participant' : (roleEl.options[0] ? roleEl.options[0].value : '');
        }
    }

    function pushDraft() {
        if (!userEl || !emailEl || !roleEl) return false;
        var userId = parseInt(userEl.value, 10) || 0;
        var email = (emailEl.value || '').trim();
        var role = roleEl.value || 'participant';
        if (!userId && !email) return false;

        var label = email;
        if (userId) {
            var opt = userEl.options[userEl.selectedIndex];
            label = (opt && (opt.getAttribute('data-label') || opt.getAttribute('data-email'))) || ('#' + userId);
            if (!email && opt) email = opt.getAttribute('data-email') || '';
        }

        var dup = members.some(function (m) {
            return (userId && m.user_id === userId) || (email && m.email && m.email.toLowerCase() === email.toLowerCase());
        });
        if (dup) {
            clearDraft();
            return true;
        }

        members.push({
            user_id: userId || null,
            email: email || null,
            role: role,
            label: label
        });
        clearDraft();
        render();
        return true;
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            if (!pushDraft()) {
                if (emailEl) emailEl.focus();
            }
        });
    }

    if (emailEl) {
        emailEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                pushDraft();
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            pushDraft();
        });
    }

    modal.addEventListener('shown.bs.modal', function () {
        var input = document.getElementById(@json($modalId . '-title-input'));
        if (input) input.focus();
    });

    modal.addEventListener('hidden.bs.modal', function () {
        members = [];
        clearDraft();
        render();
        var input = document.getElementById(@json($modalId . '-title-input'));
        if (input && !input.value) { /* keep typed title if validation bounce */ }
    });

    syncEmpty();
})();
</script>
