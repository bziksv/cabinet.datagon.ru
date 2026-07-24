(function () {
    'use strict';

    var STORAGE_PREFIX = 'cabinetPwGen_';
    var bound = false;

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function showToast(type, message) {
        var wrap = qs('.cabinet-pw-toasts .toast-top-right.' + type + '-message');
        if (!wrap) {
            return;
        }
        var msgEl = qs('.toast-message', wrap);
        if (msgEl && message) {
            msgEl.textContent = message;
        }
        wrap.hidden = false;
        wrap.style.display = 'block';
        setTimeout(function () {
            wrap.hidden = true;
            wrap.style.display = 'none';
        }, 4500);
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        return Promise.resolve();
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function saveState() {
        var form = qs('#cabinet-pw-form');
        if (!form) {
            return;
        }
        qsa('.cabinet-pw-option', form).forEach(function (el) {
            var key = el.name || el.id;
            if (!key) {
                return;
            }
            if (el.type === 'checkbox') {
                localStorage.setItem(STORAGE_PREFIX + key, el.checked ? '1' : '0');
            } else {
                localStorage.setItem(STORAGE_PREFIX + key, el.value);
            }
        });
    }

    function restoreState() {
        var form = qs('#cabinet-pw-form');
        if (!form) {
            return;
        }
        qsa('.cabinet-pw-option', form).forEach(function (el) {
            var key = el.name || el.id;
            if (!key) {
                return;
            }
            var stored = localStorage.getItem(STORAGE_PREFIX + key);
            if (stored === null) {
                return;
            }
            if (el.type === 'checkbox') {
                el.checked = stored === '1';
            } else {
                el.value = stored;
            }
        });
        syncLengthUi();
    }

    function syncLengthUi() {
        var input = qs('#cabinet-pw-length');
        var range = qs('#cabinet-pw-length-range');
        var label = qs('[data-pw-length-value]');
        if (!input) {
            return;
        }
        var val = parseInt(input.value, 10) || 6;
        if (range) {
            range.value = String(val);
        }
        if (label) {
            label.textContent = String(val);
        }
    }

    function applyPreset(preset) {
        var form = qs('#cabinet-pw-form');
        if (!form) {
            return;
        }
        var map = {
            strong: { enums: true, upperCase: true, lowerCase: true, specialSymbols: true, countSymbols: 16, savePassword: false },
            pin: { enums: true, upperCase: false, lowerCase: false, specialSymbols: false, countSymbols: 6, savePassword: false },
            letters: { enums: false, upperCase: true, lowerCase: true, specialSymbols: false, countSymbols: 15, savePassword: false },
        };
        var cfg = map[preset];
        if (!cfg) {
            return;
        }
        Object.keys(cfg).forEach(function (name) {
            var el = form.elements[name];
            if (!el) {
                return;
            }
            if (el.type === 'checkbox') {
                el.checked = !!cfg[name];
            } else {
                el.value = cfg[name];
            }
        });
        syncLengthUi();
        saveState();
    }

    function updateSavedCount(delta) {
        var countEl = qs('[data-pw-saved-count]');
        var badge = qs('[data-pw-saved-badge]');
        var current = 0;
        if (countEl) {
            current = parseInt(String(countEl.textContent).replace(/\s/g, ''), 10) || 0;
        }
        var next = Math.max(0, current + delta);
        var formatted = String(next).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        if (countEl) {
            countEl.textContent = formatted;
        }
        if (badge) {
            badge.textContent = String(next);
            badge.hidden = next === 0;
        }
    }

    function initTooltip(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var existing = window.bootstrap.Tooltip.getInstance(el);
        if (existing) {
            existing.dispose();
        }
        // У modal-кнопки уже data-bs-toggle=modal — tooltip только через API.
        var opts = {
            container: 'body',
            trigger: 'hover focus',
            customClass: 'cabinet-pw-tooltip',
            placement: el.getAttribute('data-bs-placement') || 'top',
        };
        if (el.getAttribute('data-pw-tip')) {
            opts.title = el.getAttribute('data-pw-tip');
        }
        new window.bootstrap.Tooltip(el, opts);
    }

    function initTooltips(root) {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        qsa('[data-bs-toggle="tooltip"], [data-pw-tip]', root || document).forEach(initTooltip);
    }

    function hideTooltip(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var tip = window.bootstrap.Tooltip.getInstance(el);
        if (tip) {
            tip.hide();
        }
    }

    function csrfToken() {
        var token = qs('meta[name="csrf-token"]');
        return token ? token.getAttribute('content') : '';
    }

    function bindDelegatedActions() {
        if (bound) {
            return;
        }
        bound = true;

        document.addEventListener('click', function (event) {
            var copyBtn = event.target.closest('[data-pw-copy]');
            if (copyBtn && document.querySelector('.cabinet-pw-page')) {
                event.preventDefault();
                hideTooltip(copyBtn);
                var text = copyBtn.getAttribute('data-pw-copy') || '';
                copyText(text.trim()).then(function () {
                    showToast('success', copyBtn.getAttribute('data-pw-copy-msg') || 'Copied');
                });
                return;
            }

            var saveBtn = event.target.closest('[data-pw-save]');
            if (saveBtn && document.querySelector('.cabinet-pw-page')) {
                event.preventDefault();
                hideTooltip(saveBtn);
                if (saveBtn.disabled || saveBtn.classList.contains('is-saved') || !window.jQuery) {
                    return;
                }
                var password = saveBtn.getAttribute('data-pw-save') || '';
                var url = saveBtn.getAttribute('data-pw-save-url') || '';
                saveBtn.disabled = true;
                window.jQuery.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: url,
                    data: {
                        _token: csrfToken(),
                        password: password,
                    },
                    success: function (data) {
                        if (!data || !data.success) {
                            saveBtn.disabled = false;
                            showToast('error', saveBtn.getAttribute('data-pw-save-err') || 'Error');
                            return;
                        }
                        var empty = qs('[data-pw-saved-empty]');
                        var wrap = qs('[data-pw-saved-wrap]');
                        var tbody = qs('[data-pw-saved-tbody]');
                        if (empty) {
                            empty.hidden = true;
                        }
                        if (wrap) {
                            wrap.hidden = false;
                        }
                        if (tbody) {
                            var tr = document.createElement('tr');
                            tr.id = 'tr-' + data.id;
                            tr.innerHTML =
                                '<td class="cabinet-pw-password-cell align-middle">' + escapeHtml(data.password) + '</td>' +
                                '<td class="align-middle">' +
                                    '<textarea class="form-control password-comment" name="comment" id="' + data.id + '" rows="2"' +
                                    ' placeholder="' + escapeHtml(data.comment_placeholder || '') + '"' +
                                    ' data-comment-url="' + escapeHtml(data.comment_url || '') + '"' +
                                    ' data-comment-success="' + escapeHtml(data.comment_success || '') + '"' +
                                    ' data-comment-error="' + escapeHtml(data.comment_error || '') + '"></textarea>' +
                                '</td>' +
                                '<td class="align-middle text-nowrap small text-secondary">' + escapeHtml(data.created_at || '—') + '</td>' +
                                '<td class="align-middle"><div class="cabinet-pw-actions">' +
                                    '<button type="button" class="btn btn-outline-secondary btn-sm" data-pw-copy="' + escapeHtml(data.password) + '"' +
                                    ' data-pw-copy-msg="' + escapeHtml(data.copy_msg || '') + '"' +
                                    ' data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="cabinet-pw-tooltip"' +
                                    ' title="' + escapeHtml(data.copy_title || '') + '">' +
                                    '<i class="bi bi-clipboard" aria-hidden="true"></i></button>' +
                                    '<button type="button" class="btn btn-outline-danger btn-sm remove-password click_tracking" data-click="Remove"' +
                                    ' data-order="' + data.id + '" data-bs-toggle="modal" data-bs-target="#removePasswordWindow"' +
                                    ' data-pw-tip="' + escapeHtml(data.remove_title || '') + '" title="' + escapeHtml(data.remove_title || '') + '">' +
                                    '<i class="bi bi-trash" aria-hidden="true"></i></button>' +
                                '</div></td>';
                            tbody.insertBefore(tr, tbody.firstChild);
                            initTooltips(tr);
                        }
                        updateSavedCount(1);
                        saveBtn.classList.add('is-saved');
                        var savedLabel = saveBtn.getAttribute('data-pw-saved-label') || 'Saved';
                        saveBtn.title = savedLabel;
                        saveBtn.setAttribute('data-bs-original-title', savedLabel);
                        var icon = qs('i', saveBtn);
                        if (icon) {
                            icon.className = 'bi bi-bookmark-check';
                        }
                        initTooltip(saveBtn);
                        showToast('success', data.saved_msg || saveBtn.getAttribute('data-pw-save-msg') || 'OK');
                    },
                    error: function () {
                        saveBtn.disabled = false;
                        showToast('error', saveBtn.getAttribute('data-pw-save-err') || 'Error');
                    },
                });
                return;
            }

            var removeBtn = event.target.closest('.remove-password');
            if (removeBtn && document.querySelector('.cabinet-pw-page')) {
                var passwordId = qs('#passwordId');
                if (passwordId) {
                    passwordId.value = removeBtn.getAttribute('data-order') || '';
                }
            }
        });

        document.addEventListener('change', function (event) {
            var textarea = event.target.closest('.password-comment');
            if (!textarea || !document.querySelector('.cabinet-pw-page') || !window.jQuery) {
                return;
            }
            window.jQuery.ajax({
                type: 'POST',
                dataType: 'json',
                url: textarea.getAttribute('data-comment-url'),
                data: {
                    _token: csrfToken(),
                    id: textarea.getAttribute('id'),
                    comment: textarea.value,
                },
                success: function () {
                    showToast('success', textarea.getAttribute('data-comment-success') || 'OK');
                },
                error: function () {
                    showToast('error', textarea.getAttribute('data-comment-error') || 'Error');
                },
            });
        });
    }

    function bindPresets() {
        qsa('[data-pw-preset]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyPreset(btn.getAttribute('data-pw-preset'));
            });
        });
    }

    function bindLengthControls() {
        var input = qs('#cabinet-pw-length');
        var range = qs('#cabinet-pw-length-range');
        if (!input) {
            return;
        }
        input.addEventListener('input', function () {
            syncLengthUi();
        });
        if (range) {
            range.addEventListener('input', function () {
                input.value = range.value;
                syncLengthUi();
            });
        }
        syncLengthUi();
    }

    function bindRemoveConfirm() {
        var passwordId = qs('#passwordId');
        var confirmBtn = qs('#success-remove-password');
        if (!confirmBtn || !window.jQuery) {
            return;
        }
        confirmBtn.addEventListener('click', function () {
            window.jQuery.ajax({
                type: 'POST',
                dataType: 'json',
                url: confirmBtn.getAttribute('data-remove-url'),
                data: {
                    _token: csrfToken(),
                    id: passwordId ? passwordId.value : '',
                },
                success: function () {
                    var id = passwordId ? passwordId.value : '';
                    var row = qs('#tr-' + id);
                    if (row) {
                        row.remove();
                    }
                    var tbody = qs('[data-pw-saved-tbody]');
                    if (tbody && !tbody.children.length) {
                        var empty = qs('[data-pw-saved-empty]');
                        var wrap = qs('[data-pw-saved-wrap]');
                        if (empty) {
                            empty.hidden = false;
                        }
                        if (wrap) {
                            wrap.hidden = true;
                        }
                    }
                    updateSavedCount(-1);
                    showToast('success', confirmBtn.getAttribute('data-remove-success') || 'Deleted');
                },
            });
        });
    }

    function bindForm() {
        var form = qs('#cabinet-pw-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', saveState);
        qsa('.cabinet-pw-option', form).forEach(function (el) {
            el.addEventListener('change', saveState);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        restoreState();
        bindPresets();
        bindLengthControls();
        bindDelegatedActions();
        bindRemoveConfirm();
        bindForm();
        initTooltips(qs('.cabinet-pw-page'));
    });
})();
