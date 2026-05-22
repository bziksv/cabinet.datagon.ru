/**
 * Виджеты AdminLTE 3, отсутствующие в html/js/adminlte.min.js (LTE4).
 */
(function ($) {
    'use strict';

    var SELECTOR_EXPANDABLE = '[data-widget="expandable-table"]';
    var SELECTOR_EXPANDABLE_BODY = '.expandable-body';

    function toggleExpandableRow($row) {
        var time = 500;
        var expanded = $row.attr('aria-expanded');
        var $body = $row.next(SELECTOR_EXPANDABLE_BODY).children().first().children();
        $body.stop();

        if (expanded === 'true') {
            $body.slideUp(time, function () {
                $row.next(SELECTOR_EXPANDABLE_BODY).addClass('d-none');
            });
            $row.attr('aria-expanded', 'false');
        } else {
            $row.next(SELECTOR_EXPANDABLE_BODY).removeClass('d-none');
            $body.slideDown(time);
            $row.attr('aria-expanded', 'true');
        }
    }

    function initExpandableTables(root) {
        $(root).find(SELECTOR_EXPANDABLE).addBack(SELECTOR_EXPANDABLE).each(function () {
            var $header = $(this);
            var expanded = $header.attr('aria-expanded');
            var $body = $header.next(SELECTOR_EXPANDABLE_BODY).children().first().children();

            if (expanded === 'true') {
                $body.show();
            } else if (expanded === 'false') {
                $body.hide();
                $body.parent().parent().addClass('d-none');
            }
        });
    }

    $(function () {
        initExpandableTables(document);
    });

    $(document).on('click', SELECTOR_EXPANDABLE, function (e) {
        if ($(e.target).closest('a, button, input, select, textarea, label').length) {
            return;
        }
        toggleExpandableRow($(this));
    });

    window.cabinetLte3Widgets = {
        initExpandableTables: initExpandableTables,
        toggleExpandableRow: toggleExpandableRow
    };
})(jQuery);
