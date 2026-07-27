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
            var existing = window.bootstrap.Tooltip.getInstance(el);
            if (existing) {
                existing.dispose();
            }
            new window.bootstrap.Tooltip(el, {
                container: 'body',
                trigger: 'hover focus',
                placement: el.getAttribute('data-bs-placement') || 'top',
                customClass: 'cabinet-ra-tooltip',
                title: tip,
            });
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
