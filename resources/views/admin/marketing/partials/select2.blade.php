{{-- Select2 для селектов с [data-mk-select2] (правило кабинета: ≥10 опций — с поиском) --}}
<script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
<script>
    (function ($) {
        if (!$ || !$.fn.select2) { return; }
        $('[data-mk-select2]').each(function () {
            var $s = $(this);
            $s.select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: $s.data('placeholder') || '',
                allowClear: !$s.prop('required')
            });
        });
    })(window.jQuery);
</script>
