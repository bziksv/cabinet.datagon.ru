<script>
    (function ($) {
        function syncLocalePanel($toggle) {
            var target = $toggle.data('target');
            var $panel = $('#panel-' + target);
            var $inputs = $('.locale-input--' + target);
            if ($toggle.is(':checked')) {
                $panel.prop('hidden', false);
                $inputs.prop('required', true);
            } else {
                $panel.prop('hidden', true);
                $inputs.prop('required', false);
            }
        }

        $('.cabinet-partners-locale-toggle').each(function () {
            syncLocalePanel($(this));
        });

        $(document).on('change', '.cabinet-partners-locale-toggle', function () {
            syncLocalePanel($(this));
        });
    })(jQuery);
</script>
