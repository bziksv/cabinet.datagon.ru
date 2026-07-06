{{-- Подключается после app.js: русский текст и снятие зависшего оверлея please-wait --}}
<style>
    /* .pg-loading-logo-header { width: 100% } — SVG без ограничения растягивался на весь экран */
    .pg-loading-screen .pg-loading-logo-header img {
        max-width: 12rem;
        width: auto;
        height: auto;
        max-height: 3rem;
        object-fit: contain;
    }

    /* max-width без margin: auto уезжал влево под сайдбар */
    .pg-loading-screen .loading-message {
        margin-left: auto;
        margin-right: auto;
    }
</style>
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
            document.querySelectorAll('.pg-loading-screen .loading-message').forEach(function (el) {
                el.textContent = text;
            });
        }, 0);
        return instance;
    };
})();
</script>
