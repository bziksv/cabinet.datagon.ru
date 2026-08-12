/**
 * Site Audit: «Есть в индексе, но не попали в эту проверку» —
 * collapse + table (в обходе / HTTP / robots) + presets + pagination.
 *
 * Data: <textarea hidden data-sa-extra-json>[ {url,in_crawl,status,robots,reason}, ... ]</textarea>
 */
(function () {
    'use strict';

    var PER_PAGE = 50;

    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatInt(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
    }

    function normalizeRow(item) {
        if (typeof item === 'string') {
            return {
                url: item,
                in_crawl: false,
                status: null,
                noindex: null,
                robots: 'unknown',
                reason: 'not_fetched',
            };
        }
        if (!item || typeof item !== 'object') {
            return null;
        }
        var url = String(item.url || '').trim();
        if (!url) return null;
        return {
            url: url,
            in_crawl: !!item.in_crawl,
            status: item.status == null || item.status === '' ? null : Number(item.status),
            noindex: item.noindex == null ? null : !!item.noindex,
            robots: String(item.robots || 'unknown'),
            reason: String(item.reason || 'not_fetched'),
        };
    }

    function readRows(root) {
        var el = root.querySelector('[data-sa-extra-json]');
        if (!el) return [];
        var raw = (el.value != null && el.tagName === 'TEXTAREA')
            ? el.value
            : (el.textContent || '');
        raw = String(raw).trim();
        if (!raw) return [];
        try {
            var parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) return [];
            var out = [];
            for (var i = 0; i < parsed.length; i++) {
                var row = normalizeRow(parsed[i]);
                if (row) out.push(row);
            }
            return out;
        } catch (e) {
            return [];
        }
    }

    function reasonLabel(reason) {
        switch (reason) {
            case 'robots': return 'robots.txt';
            case 'noindex': return 'noindex';
            case 'redirect': return 'редирект';
            case 'non_200': return 'не HTTP 200';
            case 'not_fetched': return 'не в обходе';
            case 'other': return 'другое';
            default: return reason || '—';
        }
    }

    function robotsLabel(robots) {
        if (robots === 'allow') return 'разрешён';
        if (robots === 'deny') return 'запрещён';
        return 'н/д';
    }

    function robotsClass(robots) {
        if (robots === 'allow') return 'is-ok';
        if (robots === 'deny') return 'is-bad';
        return 'is-muted';
    }

    function toggleRoot(root, toggle) {
        if (!root) return;
        var closed = root.classList.toggle('is-collapsed');
        if (toggle) {
            toggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
        }
    }

    if (!window.__saIndexExtraToggleBound) {
        window.__saIndexExtraToggleBound = true;
        document.addEventListener('click', function (e) {
            var toggle = e.target && e.target.closest
                ? e.target.closest('[data-sa-extra-toggle]')
                : null;
            if (!toggle) return;
            var root = toggle.closest('[data-sa-index-extra]');
            if (!root) return;
            e.preventDefault();
            toggleRoot(root, toggle);
        });
    }

    function initRoot(root) {
        if (!root || root.getAttribute('data-sa-extra-inited') === '1') return;
        root.setAttribute('data-sa-extra-inited', '1');

        var filter = root.querySelector('[data-sa-extra-filter]');
        var list = root.querySelector('[data-sa-extra-list]');
        var empty = root.querySelector('[data-sa-extra-empty]');
        var copyBtn = root.querySelector('[data-sa-extra-copy]');
        var pager = root.querySelector('[data-sa-extra-pager]');
        var prevBtn = root.querySelector('[data-sa-extra-prev]');
        var nextBtn = root.querySelector('[data-sa-extra-next]');
        var pageLabel = root.querySelector('[data-sa-extra-page-label]');
        var presetWrap = root.querySelector('[data-sa-extra-presets]');
        if (!list) return;

        var allRows = readRows(root);
        if (!allRows.length) return;

        var page = 1;
        var preset = 'robots_ok';

        function setPresetActive() {
            if (!presetWrap) return;
            var btns = presetWrap.querySelectorAll('[data-sa-extra-preset]');
            for (var i = 0; i < btns.length; i++) {
                var b = btns[i];
                var on = b.getAttribute('data-sa-extra-preset') === preset;
                b.classList.toggle('btn-primary', on);
                b.classList.toggle('btn-outline-secondary', !on);
            }
        }

        function matchedRows() {
            var q = filter ? (filter.value || '').trim().toLowerCase() : '';
            return allRows.filter(function (row) {
                if (preset === 'robots_ok') {
                    if (row.robots === 'deny') return false;
                } else if (preset === 'alive') {
                    if (!row.in_crawl || row.robots === 'deny') return false;
                } else if (preset === 'not_fetched') {
                    if (row.in_crawl) return false;
                } else if (preset === 'robots_deny') {
                    if (row.robots !== 'deny') return false;
                }
                if (!q) return true;
                return row.url.toLowerCase().indexOf(q) !== -1;
            });
        }

        function render() {
            var matched = matchedRows();
            var total = matched.length;
            var pages = Math.max(1, Math.ceil(total / PER_PAGE) || 1);
            if (page > pages) page = pages;
            if (page < 1) page = 1;
            var from = total === 0 ? 0 : ((page - 1) * PER_PAGE) + 1;
            var to = Math.min(page * PER_PAGE, total);
            var slice = matched.slice((page - 1) * PER_PAGE, page * PER_PAGE);

            var html = '';
            for (var i = 0; i < slice.length; i++) {
                var row = slice[i];
                var n = from + i;
                var crawlHtml = row.in_crawl
                    ? '<span class="cabinet-sa-index-extra__badge is-ok">да</span>'
                    : '<span class="cabinet-sa-index-extra__badge is-muted">нет</span>';
                var statusHtml = row.status == null
                    ? '<span class="text-muted">—</span>'
                    : esc(String(row.status));
                var robHtml = '<span class="cabinet-sa-index-extra__badge ' + robotsClass(row.robots) + '">'
                    + esc(robotsLabel(row.robots)) + '</span>';
                html += '<tr>'
                    + '<td class="cabinet-sa-index-extra__col-n text-muted">' + n + '</td>'
                    + '<td class="cabinet-sa-index-extra__col-url"><a href="' + esc(row.url)
                    + '" target="_blank" rel="noopener noreferrer">' + esc(row.url) + '</a></td>'
                    + '<td class="cabinet-sa-index-extra__col-flag">' + crawlHtml + '</td>'
                    + '<td class="cabinet-sa-index-extra__col-flag">' + statusHtml + '</td>'
                    + '<td class="cabinet-sa-index-extra__col-flag">' + robHtml + '</td>'
                    + '<td class="cabinet-sa-index-extra__col-reason">' + esc(reasonLabel(row.reason)) + '</td>'
                    + '</tr>';
            }
            list.innerHTML = html;

            if (pageLabel) {
                pageLabel.textContent = total === 0
                    ? '0'
                    : (from + '–' + to + ' из ' + formatInt(total));
            }
            if (prevBtn) prevBtn.disabled = page <= 1 || total === 0;
            if (nextBtn) nextBtn.disabled = page >= pages || total === 0;
            if (pager) pager.classList.toggle('d-none', total === 0);
            if (empty) empty.classList.toggle('d-none', total > 0);
            var wrap = root.querySelector('.cabinet-sa-index-extra__table-wrap');
            if (wrap) wrap.classList.toggle('d-none', total === 0);
            setPresetActive();
        }

        if (presetWrap) {
            presetWrap.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest
                    ? e.target.closest('[data-sa-extra-preset]')
                    : null;
                if (!btn || !presetWrap.contains(btn)) return;
                preset = btn.getAttribute('data-sa-extra-preset') || 'all';
                page = 1;
                render();
            });
        }
        if (filter) {
            filter.addEventListener('input', function () {
                page = 1;
                render();
            });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (prevBtn.disabled) return;
                page -= 1;
                render();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (nextBtn.disabled) return;
                page += 1;
                render();
            });
        }
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var text = matchedRows().map(function (r) { return r.url; }).join('\n');
                var done = function () {
                    var prev = copyBtn.textContent;
                    copyBtn.textContent = 'Скопировано';
                    setTimeout(function () { copyBtn.textContent = prev; }, 1200);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {});
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); done(); } catch (e) {}
                    document.body.removeChild(ta);
                }
            });
        }

        render();
    }

    function initAll() {
        var roots = document.querySelectorAll('[data-sa-index-extra]');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
