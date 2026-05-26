@php
    $syncMonitoring = $syncMonitoring ?? false;
@endphp
<script>
(function () {
    var $page = $('.cabinet-backlink-page');
    if (!$page.length) {
        return;
    }

    var countRows = 1;
    var $express = $page.find('.cabinet-bl-express-form');
    var $simple = $page.find('.cabinet-bl-simplified-form');
    var $expressInput = $express.find('[name="project_name"]');
    var $simpleInput = $simple.find('[name="project_name"]');
    var $expressSelect = $express.find('.monitoring-options');
    var $simpleSelect = $simple.find('.monitoring-options');
    var $countField = $page.find('#cabinet-bl-count-rows');
    var $tableBody = $page.find('#cabinet-bl-simplified-table tbody');
    var $removeBtn = $page.find('#cabinet-bl-remove-row');

    function setMode(mode) {
        var isTable = mode === 'table';
        $page.find('[data-cabinet-bl-mode]').each(function () {
            var active = $(this).data('cabinet-bl-mode') === mode;
            $(this).toggleClass('active', active).attr('aria-selected', active ? 'true' : 'false');
        });

        if (isTable) {
            if ($expressInput.length && $simpleInput.length) {
                $simpleInput.val($expressInput.val());
            }
            @if($syncMonitoring)
            if ($expressSelect.length && $simpleSelect.length) {
                $simpleSelect.val($expressSelect.val()).trigger('change');
            }
            @endif
            $express.addClass('is-hidden');
            $simple.addClass('is-active');
        } else {
            if ($expressInput.length && $simpleInput.length) {
                $expressInput.val($simpleInput.val());
            }
            @if($syncMonitoring)
            if ($expressSelect.length && $simpleSelect.length) {
                $expressSelect.val($simpleSelect.val()).trigger('change');
            }
            @endif
            $express.removeClass('is-hidden');
            $simple.removeClass('is-active');
        }
    }

    $page.on('click', '[data-cabinet-bl-mode]', function () {
        setMode($(this).data('cabinet-bl-mode'));
    });

    $page.on('click', '#cabinet-bl-add-row', function () {
        $removeBtn.show();
        countRows += 1;
        $countField.val(countRows);
        $tableBody.append(
            '<tr id="cabinet-bl-row-' + countRows + '">' +
            '<td><input type="text" name="site_donor_' + countRows + '" class="form-control" required></td>' +
            '<td><input type="text" name="link_' + countRows + '" class="form-control" required></td>' +
            '<td><input type="text" name="anchor_' + countRows + '" class="form-control" required></td>' +
            '<td><select class="form-select" name="nofollow_' + countRows + '"><option value="1">{{ __("Yes") }}</option><option value="0">{{ __("No") }}</option></select></td>' +
            '<td><select class="form-select" name="noindex_' + countRows + '"><option value="1">{{ __("Yes") }}</option><option value="0">{{ __("No") }}</option></select></td>' +
            '</tr>'
        );
    });

    $page.on('click', '#cabinet-bl-remove-row', function () {
        $('#cabinet-bl-row-' + countRows).remove();
        countRows -= 1;
        $countField.val(countRows);
        if (countRows <= 1) {
            $removeBtn.hide();
        }
    });

    @if($syncMonitoring)
    if ($expressSelect.length && $simpleSelect.length) {
        $expressSelect.on('select2:select', function (e) {
            $simpleSelect.val(e.params.data.id).trigger('change');
        });
        $simpleSelect.on('select2:select', function (e) {
            $expressSelect.val(e.params.data.id).trigger('change');
        });
    }

    $page.find('.monitoring-options').select2({
        placeholder: @json(__('Backlink monitoring placeholder')),
        allowClear: true,
        selectOnClose: true,
        sorter: function (el) {
            return el.sort(function (a, b) {
                a = a.text.toLowerCase();
                b = b.text.toLowerCase();
                return a < b ? -1 : (a > b ? 1 : 0);
            });
        },
    });
    @endif
})();
</script>
