{{-- Poll прогресса #sa-progress-wrap. Опционально обновляет #sa-buckets [data-bucket]. --}}
<script>
(function () {
    var wrap = document.getElementById('sa-progress-wrap');
    if (!wrap) return;
    var url = wrap.getAttribute('data-sa-status-url');
    if (!url) return;
    var reloadOnFinish = wrap.getAttribute('data-sa-reload-on-finish') === '1';
    var bar = document.getElementById('sa-progress-bar');
    var label = document.getElementById('sa-progress-label');
    var statusText = document.getElementById('sa-status-text');
    var pillLive = document.getElementById('sa-status-pill-live');
    var pctEl = document.getElementById('sa-progress-pct');
    var hintEl = document.getElementById('sa-progress-hint');
    var track = wrap.querySelector('.cabinet-sa-progress');

    function formatSaNum(n) {
        n = parseInt(n, 10) || 0;
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }

    function tick() {
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var pct = Math.max(0, Math.min(100, parseInt(j.progress_pct, 10) || 0));
                if (label) {
                    label.textContent = (j.pages_fetched || 0) + ' / ' + (j.pages_total || 0);
                }
                if (statusText) {
                    statusText.textContent = j.status_label || j.status || 'Сканирование';
                }
                if (pctEl) {
                    pctEl.textContent = pct + '%';
                }
                if (bar) {
                    bar.style.width = pct + '%';
                }
                if (track) {
                    track.setAttribute('aria-valuenow', String(pct));
                }
                if (pillLive) {
                    var st = (j.status === 'done') ? 'done'
                        : (j.status === 'failed' || j.status === 'cancelled') ? 'failed'
                        : 'run';
                    pillLive.className = 'cabinet-sa-status cabinet-sa-status--' + st + ' cabinet-sa-crawl-live__pill';
                }
                if (hintEl) {
                    if (j.pages_unchanged > 0) {
                        hintEl.textContent = 'Без изменений: ' + j.pages_unchanged + ' стр.';
                    } else if (j.status === 'discovering' || j.status === 'queued' || j.status === 'queued_wait') {
                        hintEl.textContent = 'Подготовка обхода — список URL ещё собирается';
                    } else {
                        hintEl.textContent = 'Идёт обход страниц — счётчики обновляются по мере сканирования';
                    }
                }
                if (j.buckets) {
                    Object.keys(j.buckets).forEach(function (k) {
                        document.querySelectorAll('#sa-buckets [data-bucket="' + k + '"], [data-sa-live-bucket="' + k + '"]').forEach(function (el) {
                            el.textContent = formatSaNum(j.buckets[k]);
                        });
                    });
                }
                if (j.pages_fetched != null) {
                    document.querySelectorAll('[data-sa-live-pages]').forEach(function (el) {
                        el.textContent = formatSaNum(j.pages_fetched);
                    });
                }
                if (j.finished) {
                    wrap.style.display = 'none';
                    if (reloadOnFinish) {
                        window.location.reload();
                    }
                    return;
                }
                setTimeout(tick, 2000);
            })
            .catch(function () { setTimeout(tick, 4000); });
    }

    setTimeout(tick, 1500);
})();
</script>
