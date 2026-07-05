/**
 * Состояние «идёт запрос» для кнопок кабинета: disabled + спиннер + aria-busy.
 * @see titlo.ru/.cursor/rules/redbox-cabinet-button-busy.mdc
 */
(function (window, $) {
    'use strict';

    var HTML_ATTR = 'data-cabinet-btn-busy-html';
    var DISABLED_ATTR = 'data-cabinet-btn-busy-disabled';

    function resolveEl(btn) {
        if (!btn) {
            return null;
        }
        if (btn instanceof window.jQuery) {
            return btn.length ? btn : null;
        }
        if (btn.nodeType === 1) {
            return $(btn);
        }
        return null;
    }

    function spinnerHtml() {
        return '<span class="cabinet-mon-loader cabinet-mon-loader--sm me-1" role="presentation">' +
            '<i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i></span>';
    }

    function modalDismissControls($modal, disabled) {
        if (!$modal || !$modal.length) {
            return;
        }

        $modal.find('[data-bs-dismiss="modal"]').each(function () {
            var $control = $(this);
            if (disabled) {
                if (!$control.attr(DISABLED_ATTR)) {
                    $control.attr(DISABLED_ATTR, $control.prop('disabled') ? '1' : '0');
                }
                $control.prop('disabled', true);
                return;
            }

            var wasDisabled = $control.attr(DISABLED_ATTR);
            if (wasDisabled !== undefined) {
                $control.prop('disabled', wasDisabled === '1');
                $control.removeAttr(DISABLED_ATTR);
            } else {
                $control.prop('disabled', false);
            }
        });
    }

    function setBusy(btn, options) {
        var $btn = resolveEl(btn);
        if (!$btn || !$btn.length || $btn.attr('aria-busy') === 'true') {
            return false;
        }

        options = options || {};
        if (!$btn.attr(HTML_ATTR)) {
            $btn.attr(HTML_ATTR, $btn.html());
        }

        var label = options.label != null ? String(options.label) : '…';
        $btn.prop('disabled', true).attr('aria-busy', 'true').addClass('cabinet-btn-busy');
        $btn.html(spinnerHtml() + label);

        if (options.blockModal) {
            modalDismissControls($btn.closest('.modal'), true);
        }

        return true;
    }

    function clearBusy(btn, options) {
        var $btn = resolveEl(btn);
        if (!$btn || !$btn.length) {
            return;
        }

        options = options || {};
        var html = $btn.attr(HTML_ATTR);
        if (html !== undefined) {
            $btn.html(html);
            $btn.removeAttr(HTML_ATTR);
        }

        $btn.prop('disabled', false).removeAttr('aria-busy').removeClass('cabinet-btn-busy');

        if (options.blockModal) {
            modalDismissControls($btn.closest('.modal'), false);
        }
    }

    window.cabinetButtonBusy = {
        set: setBusy,
        clear: clearBusy,
        spinnerHtml: spinnerHtml,
    };
}(window, window.jQuery));
