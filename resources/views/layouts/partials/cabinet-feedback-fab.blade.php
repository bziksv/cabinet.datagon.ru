{{-- Глобальная FAB: идея / данные / баг → тикет с URL страницы --}}
@auth
@php
    $cabinetFeedback = \App\Support\CabinetModuleFeedback::resolveFromRequest();
@endphp
<div class="cabinet-feedback" id="cabinet-feedback-root">
    <button type="button"
            class="cabinet-feedback-fab"
            id="cabinet-feedback-fab"
            data-toggle="modal"
            data-target="#cabinetFeedbackModal"
            aria-label="Идея, данные или баг">
        <svg class="cabinet-feedback-fab__icon" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6C8.28 12.45 7.5 10.8 7.5 9c0-2.48 2.02-4.5 4.5-4.5s4.5 2.02 4.5 4.5c0 1.8-.78 3.45-2.15 4.1z"/>
            <circle cx="12" cy="9" r="1.35" fill="#fbbf24"/>
        </svg>
        <span class="cabinet-feedback-fab__tip" aria-hidden="true">Идея / данные / баг?</span>
    </button>
</div>

<div class="modal fade" id="cabinetFeedbackModal" tabindex="-1" role="dialog" aria-labelledby="cabinetFeedbackModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="cabinetFeedbackModalTitle">Обратная связь</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Закрыть">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="cabinet-feedback-form" method="post" action="{{ route('support.module-feedback') }}">
                @csrf
                <input type="hidden" name="module" id="cabinet-feedback-module" value="{{ $cabinetFeedback['code'] }}">
                <input type="hidden" name="page_url" id="cabinet-feedback-page-url" value="">
                <div class="modal-body py-3">
                    <p class="small text-secondary mb-2">
                        Уйдёт в поддержку тикетом
                        @if(($cabinetFeedback['label'] ?? '') !== '')
                            · {{ $cabinetFeedback['label'] }}
                        @endif.
                        К сообщению приложим адрес этой страницы.
                    </p>
                    <div class="cabinet-feedback-kinds mb-2" role="radiogroup" aria-label="Тип обращения">
                        <label class="cabinet-feedback-kind is-active">
                            <input type="radio" name="kind" value="idea" checked>
                            <span>Идея</span>
                        </label>
                        <label class="cabinet-feedback-kind">
                            <input type="radio" name="kind" value="missing_data">
                            <span>Данные</span>
                        </label>
                        <label class="cabinet-feedback-kind">
                            <input type="radio" name="kind" value="bug">
                            <span>Баг</span>
                        </label>
                    </div>
                    <label class="mb-1 small font-weight-bold" for="cabinet-feedback-body">Сообщение</label>
                    <textarea class="form-control"
                              id="cabinet-feedback-body"
                              name="body"
                              rows="4"
                              required
                              maxlength="5000"
                              placeholder="Коротко: идея, чего не хватает или что сломалось…"></textarea>
                    <div class="invalid-feedback d-none" id="cabinet-feedback-error"></div>
                    <div class="small text-success mt-2 d-none" id="cabinet-feedback-ok"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="cabinet-feedback-submit">Отправить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('cabinet-feedback-root');
    var form = document.getElementById('cabinet-feedback-form');
    var fab = document.getElementById('cabinet-feedback-fab');
    var modal = document.getElementById('cabinetFeedbackModal');
    if (!form || form.getAttribute('data-cabinet-feedback-bound') === '1') return;
    form.setAttribute('data-cabinet-feedback-bound', '1');

    if (root && root.parentElement !== document.body) {
        document.body.appendChild(root);
    }
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    var pageInput = document.getElementById('cabinet-feedback-page-url');
    var body = document.getElementById('cabinet-feedback-body');
    var err = document.getElementById('cabinet-feedback-error');
    var ok = document.getElementById('cabinet-feedback-ok');
    var btn = document.getElementById('cabinet-feedback-submit');

    function resetUi() {
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
        if (ok) {
            ok.textContent = '';
            ok.classList.add('d-none');
        }
        if (btn) btn.disabled = false;
    }

    function syncKindActive() {
        var kinds = form.querySelectorAll('.cabinet-feedback-kind');
        for (var i = 0; i < kinds.length; i++) {
            var inp = kinds[i].querySelector('input');
            kinds[i].classList.toggle('is-active', !!(inp && inp.checked));
        }
    }
    syncKindActive();
    form.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'kind') syncKindActive();
    });

    if (modal && window.jQuery) {
        jQuery(modal).on('show.bs.modal', function () {
            if (pageInput) pageInput.value = window.location.href;
            resetUi();
            if (fab) fab.classList.add('is-open');
        });
        jQuery(modal).on('shown.bs.modal', function () {
            if (body) body.focus();
        });
        jQuery(modal).on('hidden.bs.modal', function () {
            if (body) body.value = '';
            resetUi();
            if (fab) fab.classList.remove('is-open');
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        resetUi();
        if (pageInput) pageInput.value = window.location.href;
        if (btn) btn.disabled = true;

        var fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, status: r.status, j: j };
            }).catch(function () {
                return { ok: r.ok, status: r.status, j: null };
            });
        }).then(function (x) {
            if (x.ok && x.j && x.j.ok) {
                if (ok) {
                    ok.classList.remove('d-none');
                    ok.innerHTML = 'Отправлено. <a href="' + (x.j.url || '#') + '">Открыть тикет</a>';
                }
                if (body) body.value = '';
                setTimeout(function () {
                    if (window.jQuery) jQuery(modal).modal('hide');
                }, 1200);
                return;
            }
            var msg = 'Не удалось отправить';
            if (x.j && x.j.message) msg = x.j.message;
            if (x.j && x.j.errors) {
                var first = Object.keys(x.j.errors)[0];
                if (first && x.j.errors[first] && x.j.errors[first][0]) {
                    msg = x.j.errors[first][0];
                }
            }
            if (err) {
                err.textContent = msg;
                err.classList.remove('d-none');
            }
            if (btn) btn.disabled = false;
        }).catch(function () {
            if (err) {
                err.textContent = 'Сеть или сервер недоступны';
                err.classList.remove('d-none');
            }
            if (btn) btn.disabled = false;
        });
    });
})();
</script>
@endauth
