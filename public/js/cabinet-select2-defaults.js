/**
 * Select2: тема bootstrap4 + BS5-стили (cabinet-select2-bs5.css).
 * Фокус в поле поиска при первом клике (без второго клика в dropdown).
 *
 * На /monitoring/{id} select2 подключается в @slot('js') после layout — ждём $.fn.select2.
 */
(function ($) {
    'use strict';
    if (typeof $ === 'undefined') {
        return;
    }

    var wired = false;

    function focusOpenSelect2Search(evt) {
        var $select = $(evt.target);
        var select2 = $select.data('select2');
        var attempts = 0;

        function tryFocus() {
            var el = null;
            if (select2 && select2.dropdown && select2.dropdown.$search) {
                el = select2.dropdown.$search.get(0);
            }
            if (!el) {
                el = document.querySelector('.select2-container--open .select2-search__field');
            }
            if (el) {
                el.focus({ preventScroll: true });
                return;
            }
            if (attempts++ < 8) {
                window.requestAnimationFrame(tryFocus);
            }
        }

        window.requestAnimationFrame(tryFocus);
    }

    function wireFocusHandlers() {
        if (wired || window.__cabinetSelect2DefaultsWired) {
            wired = true;
            return;
        }
        wired = true;
        window.__cabinetSelect2DefaultsWired = true;
        $(document)
            .on('select2:open.cabinetSelect2Defaults', focusOpenSelect2Search)
            .on('mousedown.cabinetSelect2Defaults', '.select2-container .select2-selection', function (e) {
                var $container = $(this).closest('.select2-container');
                if (!$container.hasClass('select2-container--open')) {
                    e.preventDefault();
                }
            });
    }

    function applyDefaults() {
        if (!$.fn.select2) {
            return false;
        }
        $.fn.select2.defaults.set('theme', 'bootstrap4');
        $.fn.select2.defaults.set('width', '100%');
        wireFocusHandlers();
        return true;
    }

    if (!applyDefaults()) {
        var attempts = 0;
        var timer = window.setInterval(function () {
            if (applyDefaults() || ++attempts > 400) {
                window.clearInterval(timer);
            }
        }, 50);
    }
})(window.jQuery);
