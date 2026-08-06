(function (window) {
    'use strict';

    function escapeTipAttr(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    /** HTML attribute fragment: data-ra-tip="…" */
    function relevanceActionTipAttr(text) {
        if (!text) {
            return '';
        }
        return ' data-ra-tip="' + escapeTipAttr(text) + '"';
    }

    function bindBootstrapTip(el, tip, placement) {
        if (!el || !tip || !window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var existing = window.bootstrap.Tooltip.getInstance(el);
        if (existing) {
            existing.dispose();
        }
        new window.bootstrap.Tooltip(el, {
            container: 'body',
            trigger: 'hover focus',
            placement: placement || el.getAttribute('data-bs-placement') || 'top',
            customClass: 'cabinet-ra-tooltip',
            title: tip,
            // Без анимации Popper успевает применить transform с первого show;
            // fixed — корректные координаты внутри sticky/overflow TLP.
            animation: false,
            popperConfig: {
                strategy: 'fixed',
            },
        });
        el.addEventListener('shown.bs.tooltip', function onShown() {
            var inst = window.bootstrap.Tooltip.getInstance(el);
            if (inst && inst._popper) {
                inst._popper.update();
            }
        });
    }

    function initRelevanceActionTips(root) {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var scope = root && root.querySelectorAll ? root : document;

        scope.querySelectorAll('[data-ra-tip]').forEach(function (el) {
            var tip = el.getAttribute('data-ra-tip');
            if (!tip) {
                return;
            }
            bindBootstrapTip(el, tip, el.getAttribute('data-bs-placement') || 'top');
        });

        // Подсказки в sticky-заголовках TLP: CSS-тултип режется соседними th / overflow.
        // Переносим текст в Bootstrap Tooltip на body.
        scope.querySelectorAll('.relevance-tlp-col-tip').forEach(function (tipEl) {
            if (tipEl.getAttribute('data-ra-bs-bound') === '1') {
                return;
            }
            var contentEl = tipEl.querySelector('.ui_tooltip_content') || tipEl;
            var tip = (contentEl.textContent || '').replace(/\s+/g, ' ').trim();
            if (!tip) {
                return;
            }
            var host = tipEl.closest('.ui_tooltip_w') || tipEl.previousElementSibling || tipEl.parentElement;
            if (!host) {
                return;
            }
            tipEl.setAttribute('data-ra-bs-bound', '1');
            tipEl.setAttribute('aria-hidden', 'true');
            // !important: иначе .ui_tooltip_w:hover > .ui_tooltip { display:block } снова показывает CSS-тип.
            tipEl.style.setProperty('display', 'none', 'important');
            host.classList.add('relevance-tlp-col-tip-host');
            if (!host.getAttribute('tabindex')) {
                host.setAttribute('tabindex', '0');
            }
            bindBootstrapTip(host, tip, 'bottom');
        });
    }

    function hideRelevanceActionTip(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }
        var tip = window.bootstrap.Tooltip.getInstance(el);
        if (tip) {
            tip.hide();
        }
    }

    window.relevanceActionTipAttr = relevanceActionTipAttr;
    window.initRelevanceActionTips = initRelevanceActionTips;
    window.hideRelevanceActionTip = hideRelevanceActionTip;
})(window);
