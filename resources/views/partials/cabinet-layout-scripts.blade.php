{{-- Все скрипты layout после jQuery/bootstrap/adminlte — не в sidebar/menu-right (иначе $ is not defined) --}}
<script>
$(function () {
    $(document).on('click', 'li.folder.has-treeview > a.sidebar-folder-toggle', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var $li = $(this).closest('li.folder');
        var $sub = $li.children('.nav-treeview');
        if (!$sub.length) {
            return false;
        }
        if ($li.hasClass('menu-open')) {
            $li.removeClass('menu-open menu-is-opening');
            $sub.stop(true, true).slideUp(200);
        } else {
            $li.addClass('menu-open menu-is-opening');
            $sub.stop(true, true).slideDown(200);
        }
        return false;
    });

    $('.x-input__field.form-control.form-control-sidebar').on('keyup', function () {
        var input = $(this).val().trim().toLowerCase();
        if (input !== '') {
            $('.folder').each(function () {
                if ($(this).attr('data-action') === 'false') {
                    $(this).addClass('menu-is-opening menu-open');
                    $(this).children('ul').eq(0).show();
                }
            });
            $('.nav-item.menu-item.ml-2').children('ul').each(function () {
                var mainBlock = $(this).parent();
                var showMain = false;
                $(this).children('li').each(function () {
                    var html = $(this).find('.module-name').first().text().trim().toLowerCase();
                    if (html.indexOf(input) !== -1) {
                        showMain = true;
                    }
                });
                mainBlock.toggle(showMain);
            });
        } else {
            $('.folder').each(function () {
                $(this).show();
                if ($(this).attr('data-action') === 'false') {
                    $(this).removeClass('menu-is-opening menu-open');
                    $(this).children('.nav-treeview').hide();
                }
            });
        }
    });

    $('#header-nav-bar > ul.navbar-nav.ml-auto > div > div > table > tbody > tr').each(function () {
        if ($(this).css('background-color') === 'rgb(253, 245, 230)') {
            var limitCell = $(this).children('td').eq(1).html();
            if ($.trim(limitCell) === 'Без ограничений') {
                $('#userModuleLimit').html("{{ __('No restrictions') }}");
            } else {
                $('#userModuleLimit').html("{{ __('from') }} " + limitCell);
                $('#userModuleUsed').html("{{ __('Left') }} " + $(this).children('td').eq(2).html());
            }
            return false;
        }
    });
});
</script>
