{{-- Подключается после app.js: русский текст и снятие зависшего оверлея please-wait --}}
<script>
(function () {
    if (typeof window.loading !== 'function') {
        return;
    }
    var originalLoading = window.loading;
    window.loading = function () {
        if (window.pleaseWait && typeof window.pleaseWait.finish === 'function') {
            try {
                window.pleaseWait.finish();
            } catch (e) {
                /* ignore */
            }
            window.pleaseWait = null;
        }
        var instance = originalLoading.apply(this, arguments);
        var text = window.cabinetPleaseWaitMessage || 'Загрузка данных…';
        setTimeout(function () {
            document.querySelectorAll('.loading-message').forEach(function (el) {
                el.textContent = text;
            });
        }, 0);
        return instance;
    };
})();
</script>
