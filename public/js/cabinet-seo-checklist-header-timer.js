/**
 * SEO checklist: active timer in app header (all pages).
 */
(function () {
    function formatDuration(total) {
        total = Math.max(0, Math.floor(total || 0));
        var h = Math.floor(total / 3600);
        var m = Math.floor((total % 3600) / 60);
        var s = total % 60;
        if (h > 0) {
            return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
        return m + ':' + String(s).padStart(2, '0');
    }

    function parseStartedAt(iso) {
        if (!iso) return null;
        var t = Date.parse(iso);
        return Number.isFinite(t) ? t : null;
    }

    function elapsedFrom(el) {
        var base = parseInt(el.getAttribute('data-base-seconds') || '0', 10) || 0;
        var started = parseStartedAt(el.getAttribute('data-started-at'));
        if (!started) return base;
        return base + Math.max(0, Math.floor((Date.now() - started) / 1000));
    }

    function tick(el) {
        var out = el.querySelector('[data-sc-header-elapsed]');
        if (out) out.textContent = formatDuration(elapsedFrom(el));
    }

    function removeHeaderTimer() {
        var el = document.querySelector('[data-sc-header-timer]');
        if (el && el.parentNode) el.parentNode.removeChild(el);
        window.cabinetSeoChecklistActiveTimer = null;
    }

    function bindHeaderTimer(el) {
        if (!el || el.getAttribute('data-bound') === '1') return;
        el.setAttribute('data-bound', '1');
        tick(el);
        window.setInterval(function () { tick(el); }, 1000);

        var stopBtn = el.querySelector('[data-sc-header-timer-stop]');
        if (!stopBtn) return;

        stopBtn.addEventListener('click', function () {
            var url = el.getAttribute('data-stop-url');
            var csrf = el.getAttribute('data-csrf') ||
                (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            if (!url) return;
            stopBtn.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: '{}',
            }).then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok && data && data.ok, data: data };
                });
            }).then(function (result) {
                if (!result.ok) {
                    stopBtn.disabled = false;
                    alert((result.data && result.data.message) || 'Error');
                    return;
                }
                removeHeaderTimer();
                if (typeof window.cabinetSeoChecklistOnTimerStopped === 'function') {
                    window.cabinetSeoChecklistOnTimerStopped(result.data);
                }
            }).catch(function () {
                stopBtn.disabled = false;
            });
        });
    }

    function init() {
        var el = document.querySelector('[data-sc-header-timer]');
        if (el) bindHeaderTimer(el);
    }

    window.cabinetSeoChecklistFormatDuration = formatDuration;
    window.cabinetSeoChecklistRemoveHeaderTimer = removeHeaderTimer;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
