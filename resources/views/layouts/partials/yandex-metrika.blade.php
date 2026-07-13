{{-- Счётчик Titlo (как на titlo.ru). Старый 89500732 оставлен для исторических целей (регистрация/оплата). --}}
@php
    $ymTitlo = 54591493;
    $ymLegacy = 89500732;
@endphp
@if(config('app.env') !== 'local')
<script type="text/javascript">
    (function (m, e, t, r, i, k, a) {
        m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
        m[i].l = 1 * new Date();
        for (var j = 0; j < document.scripts.length; j++) {
            if (document.scripts[j].src === r) { return; }
        }
        k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r;
        a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');
    ym({{ $ymTitlo }}, 'init', {clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true});
    ym({{ $ymLegacy }}, 'init', {clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true});
</script>
<noscript>
    <div>
        <img src="https://mc.yandex.ru/watch/{{ $ymTitlo }}" style="position:absolute;left:-9999px;" alt=""/>
        <img src="https://mc.yandex.ru/watch/{{ $ymLegacy }}" style="position:absolute;left:-9999px;" alt=""/>
    </div>
</noscript>
@endif
