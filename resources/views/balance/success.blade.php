<div class="modal fade" id="balance-success-modal" tabindex="-1" aria-labelledby="balance-success-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="balance-success-title">{{ __('Payment credited') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="text-success mb-3">
                    <i class="bi bi-check-circle-fill display-4"></i>
                </div>
                <p class="mb-0">
                    {{ __('Do not forget to choose a tariff') }}:
                    <a href="{{ route('tariff.index') }}" class="fw-semibold">{{ __('Choose') }}</a>
                </p>
            </div>
        </div>
    </div>

    <form action="#" id="counting-metrics-block" class="visually-hidden" onsubmit="return false;">
        <input id="counting-metrics" type="submit" onclick="ym(89500732,'reachGoal','success_payment_1231'); return true;" value="Заказать"/>
    </form>
</div>

<script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () {
            (m[i].a = m[i].a || []).push(arguments);
        };
        m[i].l = 1 * new Date();
        for (var j = 0; j < document.scripts.length; j++) {
            if (document.scripts[j].src === r) {
                return;
            }
        }
        k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');

    ym(89500732, 'init', {
        clickmap: true,
        trackLinks: true,
        accurateTrackBounce: true,
        webvisor: true,
    });
</script>
<noscript>
    <div><img src="https://mc.yandex.ru/watch/89500732" style="position:absolute; left:-9999px;" alt=""/></div>
</noscript>
