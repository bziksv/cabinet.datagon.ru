{{-- Скрипты layout после jQuery / AdminLTE 4 --}}
<script>
$(function () {
    $(document).on('click', 'li.folder .sidebar-folder-toggle', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var $li = $(this).closest('li.folder');
        var $sub = $li.children('.nav-treeview');
        if (!$sub.length) {
            return false;
        }
        if ($li.hasClass('menu-open')) {
            $li.removeClass('menu-open');
            $sub.stop(true, true).slideUp(200);
        } else {
            $li.addClass('menu-open');
            $sub.stop(true, true).slideDown(200);
        }
        return false;
    });

    var $limitsHint = $('#cabinet-header-limits-hint');
    var $used = $('#userModuleUsed');
    var $limit = $('#userModuleLimit');
    $('#header-nav-bar .cabinet-header-limits-menu table tbody tr').each(function () {
        var bg = $(this).css('background-color');
        if (bg === 'rgb(253, 245, 230)' || bg === 'rgb(255, 243, 205)') {
            var limitCell = $.trim($(this).children('td').eq(1).text());
            var leftCell = $.trim($(this).children('td').eq(2).text());
            if (limitCell === 'Без ограничений' || limitCell === "{{ __('No restrictions') }}") {
                $limit.text("{{ __('No restrictions') }}");
                $used.text('');
            } else {
                $limit.text("{{ __('from') }} " + limitCell);
                $used.text("{{ __('Left') }} " + leftCell);
            }
            $limitsHint.removeClass('is-empty');
            return false;
        }
    });
});
</script>
