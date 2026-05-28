(function () {
    'use strict';

    var STORAGE_PREFIX = 'cabinetPwGen_';

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

    function bindCopyButtons() {
        qsa('[data-pw-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-pw-copy') || '';
                copyText(text.trim()).then(function () {
                    showToast('success', btn.getAttribute('data-pw-copy-msg') || 'Copied');
                });
            });
        });
    }

    function bindComments() {
        var token = qs('meta[name="csrf-token"]');
        qsa('.password-comment').forEach(function (textarea) {
            textarea.addEventListener('change', function () {
                if (!window.jQuery || !token) {
                    return;
                }
                window.jQuery.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: textarea.getAttribute('data-comment-url'),
                    data: {
                        _token: token.getAttribute('content'),
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
        });
    }

    function bindRemove() {
        var passwordId = qs('#passwordId');
        qsa('.remove-password').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (passwordId) {
                    passwordId.value = btn.getAttribute('data-order') || '';
                }
            });
        });
        var confirmBtn = qs('#success-remove-password');
        if (!confirmBtn || !window.jQuery) {
            return;
        }
        var token = qs('meta[name="csrf-token"]');
        confirmBtn.addEventListener('click', function () {
            window.jQuery.ajax({
                type: 'POST',
                dataType: 'json',
                url: confirmBtn.getAttribute('data-remove-url'),
                data: {
                    _token: token ? token.getAttribute('content') : '',
                    id: passwordId ? passwordId.value : '',
                },
                success: function () {
                    var id = passwordId ? passwordId.value : '';
                    var row = qs('#tr-' + id);
                    if (row) {
                        row.remove();
                    }
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
        bindCopyButtons();
        bindComments();
        bindRemove();
        bindForm();
    });
})();
