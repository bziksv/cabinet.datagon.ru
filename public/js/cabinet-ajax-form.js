/**
 * Отправка форм без перезагрузки страницы.
 *
 * <form data-ajax-form …> — POST через fetch, сервер отвечает JSON { ok, message }.
 *
 * Опции на форме:
 *   data-ajax-remove="selector"     — после успеха удалить form.closest(selector)
 *   data-ajax-refresh="sel1|sel2"   — после успеха перезапросить текущую страницу
 *                                     и подменить эти блоки (без reload и прыжка скролла)
 *   data-ajax-reset="1"             — сбросить форму после успеха
 *   data-ajax-silent="1"            — не показывать тост об успехе
 *   data-ajax-no-redirect="1"       — игнорировать redirect из ответа (остаться на странице)
 *
 * Если в ответе есть redirect — переходим туда (действие уводит со страницы).
 *
 * События (bubbling, на document после refresh — на document):
 *   cabinet:ajax-form:success  detail: { form, data }
 *   cabinet:ajax-refreshed     detail: { selectors }
 *
 * Совместимо с data-cabinet-confirm: модалка вызывает requestSubmit → сюда.
 */
(function (global) {
    'use strict';

    if (global.CabinetAjaxForm) {
        return;
    }

    var toastWrap = null;

    function ensureToastStyles() {
        if (document.getElementById('cabinet-ajax-toast-css')) {
            return;
        }
        var style = document.createElement('style');
        style.id = 'cabinet-ajax-toast-css';
        style.textContent = ''
            + '.cabinet-ajax-toasts{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);'
            + 'z-index:2000;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none}'
            + '.cabinet-ajax-toast{background:#111827;color:#fff;border-radius:8px;padding:8px 14px;'
            + 'font-size:.85rem;line-height:1.35;box-shadow:0 8px 24px rgba(15,23,42,.25);'
            + 'max-width:min(520px,calc(100vw - 32px));opacity:0;transform:translateY(6px);'
            + 'transition:opacity .15s ease,transform .15s ease}'
            + '.cabinet-ajax-toast.is-shown{opacity:1;transform:none}'
            + '.cabinet-ajax-toast--error{background:#b91c1c}'
            + 'form.is-ajax-busy{opacity:.65;pointer-events:none}';
        document.head.appendChild(style);
    }

    function toast(message, isError) {
        if (!message) {
            return;
        }
        ensureToastStyles();
        if (!toastWrap || !document.body.contains(toastWrap)) {
            toastWrap = document.createElement('div');
            toastWrap.className = 'cabinet-ajax-toasts';
            toastWrap.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastWrap);
        }
        var el = document.createElement('div');
        el.className = 'cabinet-ajax-toast' + (isError ? ' cabinet-ajax-toast--error' : '');
        el.setAttribute('role', isError ? 'alert' : 'status');
        el.textContent = String(message);
        toastWrap.appendChild(el);
        requestAnimationFrame(function () {
            el.classList.add('is-shown');
        });
        global.setTimeout(function () {
            el.classList.remove('is-shown');
            global.setTimeout(function () {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 200);
        }, isError ? 5000 : 2200);
    }

    function csrfFor(form) {
        var input = form.querySelector('input[name="_token"]');
        if (input && input.value) {
            return input.value;
        }
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function stateKey(el) {
        if (el.id) {
            return '#' + el.id;
        }
        var attrs = ['data-team-id', 'data-stage-key', 'data-id'];
        for (var i = 0; i < attrs.length; i++) {
            var v = el.getAttribute(attrs[i]);
            if (v) {
                return attrs[i] + ':' + v;
            }
        }
        return '';
    }

    function captureState(root) {
        var open = {};
        var editing = {};
        root.querySelectorAll('details').forEach(function (d) {
            var k = stateKey(d);
            if (k) {
                open[k] = d.open;
            }
        });
        root.querySelectorAll('.is-editing').forEach(function (el) {
            var k = stateKey(el);
            if (k) {
                editing[k] = true;
            }
        });
        return { open: open, editing: editing };
    }

    function restoreState(root, state) {
        root.querySelectorAll('details').forEach(function (d) {
            var k = stateKey(d);
            if (k && Object.prototype.hasOwnProperty.call(state.open, k)) {
                d.open = !!state.open[k];
            }
        });
        Object.keys(state.editing).forEach(function (k) {
            var el = null;
            if (k.charAt(0) === '#') {
                el = root.querySelector(k);
            }
            if (el) {
                el.classList.add('is-editing');
                var btn = el.querySelector('[aria-expanded]');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'true');
                }
            }
        });
    }

    function fetchPage() {
        return fetch(global.location.href, {
            method: 'GET',
            headers: { 'Accept': 'text/html', 'X-Requested-With': 'fetch' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.text();
            })
            .then(function (html) {
                return new DOMParser().parseFromString(html, 'text/html');
            });
    }

    function softRefresh(selectors) {
        var list = (selectors || []).filter(Boolean);
        if (!list.length) {
            return Promise.resolve();
        }
        return fetchPage()
            .then(function (doc) {
                var structureChanged = list.some(function (sel) {
                    return document.querySelectorAll(sel).length !== doc.querySelectorAll(sel).length;
                });
                if (structureChanged) {
                    // Блок появился/исчез (например, первая команда) — точечно не подменить.
                    global.location.reload();
                    return;
                }
                list.forEach(function (sel) {
                    var current = document.querySelectorAll(sel);
                    var fresh = doc.querySelectorAll(sel);
                    current.forEach(function (oldEl, idx) {
                        var newEl = fresh[idx];
                        if (!newEl) {
                            return;
                        }
                        var state = captureState(oldEl);
                        var imported = document.importNode(newEl, true);
                        oldEl.parentNode.replaceChild(imported, oldEl);
                        restoreState(imported, state);
                    });
                });
                document.dispatchEvent(new CustomEvent('cabinet:ajax-refreshed', {
                    detail: { selectors: list }
                }));
            });
    }

    function setBusy(form, submitter, busy) {
        form.classList.toggle('is-ajax-busy', busy);
        var buttons = submitter
            ? [submitter]
            : Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], button:not([type])'));
        buttons.forEach(function (btn) {
            btn.disabled = busy;
            if (busy) {
                btn.setAttribute('aria-busy', 'true');
            } else {
                btn.removeAttribute('aria-busy');
                btn.classList.remove('is-busy');
                var orig = btn.getAttribute('data-original-label');
                if (orig !== null) {
                    btn.textContent = orig;
                    btn.removeAttribute('data-original-label');
                }
            }
        });
    }

    function submit(form, submitter) {
        if (form.getAttribute('data-ajax-pending') === '1') {
            return;
        }
        form.setAttribute('data-ajax-pending', '1');
        setBusy(form, submitter, true);

        var body = new FormData(form);
        if (submitter && submitter.name) {
            body.append(submitter.name, submitter.value || '');
        }

        fetch(form.getAttribute('action') || global.location.href, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfFor(form)
            },
            credentials: 'same-origin',
            body: body
        })
            .then(function (r) {
                return r.json().catch(function () {
                    return null;
                }).then(function (data) {
                    if (!data && r.status === 422) {
                        data = { ok: false };
                    }
                    if (data && data.errors && !data.message) {
                        var first = Object.keys(data.errors)[0];
                        data.message = first ? data.errors[first][0] : '';
                    }
                    return { ok: r.ok && !!data && data.ok !== false, data: data || {} };
                });
            })
            .then(function (result) {
                form.removeAttribute('data-ajax-pending');
                if (!result.ok) {
                    setBusy(form, submitter, false);
                    toast(result.data.message || 'Не удалось сохранить', true);
                    return;
                }

                form.dispatchEvent(new CustomEvent('cabinet:ajax-form:success', {
                    bubbles: true,
                    detail: { form: form, data: result.data }
                }));

                if (result.data.redirect && form.getAttribute('data-ajax-no-redirect') !== '1') {
                    global.location.assign(result.data.redirect);
                    return;
                }

                if (form.getAttribute('data-ajax-silent') !== '1') {
                    toast(result.data.message || 'Сохранено', false);
                }

                var refresh = (form.getAttribute('data-ajax-refresh') || '')
                    .split('|')
                    .map(function (s) { return s.trim(); })
                    .filter(Boolean);
                var removeSel = form.getAttribute('data-ajax-remove');

                if (removeSel) {
                    var target = form.closest(removeSel);
                    if (target && target.parentNode) {
                        target.parentNode.removeChild(target);
                    }
                } else {
                    if (form.getAttribute('data-ajax-reset') === '1') {
                        form.reset();
                    }
                    setBusy(form, submitter, false);
                }

                if (refresh.length) {
                    softRefresh(refresh).catch(function () {
                        global.location.reload();
                    });
                }
            })
            .catch(function () {
                form.removeAttribute('data-ajax-pending');
                setBusy(form, submitter, false);
                toast('Ошибка сети — попробуйте ещё раз', true);
            });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || !form.hasAttribute('data-ajax-form')) {
            return;
        }
        if (e.defaultPrevented) {
            return;
        }
        e.preventDefault();
        submit(form, e.submitter || null);
    });

    global.CabinetAjaxForm = {
        submit: submit,
        fetchPage: fetchPage,
        softRefresh: softRefresh,
        toast: toast
    };
})(window);
