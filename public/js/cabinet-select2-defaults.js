/**
 * Select2: тема bootstrap4 + BS5-стили (cabinet-select2-bs5.css).
 */
(function ($) {
    'use strict';
    if (typeof $ === 'undefined' || !$.fn.select2) {
        return;
    }
    $.fn.select2.defaults.set('theme', 'bootstrap4');
    $.fn.select2.defaults.set('width', '100%');
})(window.jQuery);
