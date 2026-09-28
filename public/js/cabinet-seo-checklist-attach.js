(function () {
    'use strict';

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' Б';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' КБ';
        return (bytes / 1024 / 1024).toFixed(1).replace('.', ',') + ' МБ';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function stateOf(wrap) {
        if (!wrap.__scFiles) wrap.__scFiles = [];
        return wrap.__scFiles;
    }

    function render(wrap) {
        var list = wrap.querySelector('[data-sc-attach-list]');
        if (!list) return;
        var files = stateOf(wrap);
        list.innerHTML = files.map(function (f, i) {
            return '<span class="cabinet-sc-attach__chip">' +
                '<i class="bi bi-file-earmark" aria-hidden="true"></i>' +
                '<span class="cabinet-sc-attach__chip-name">' + escapeHtml(f.name) + '</span>' +
                '<span class="cabinet-sc-attach__chip-size">' + formatSize(f.size) + '</span>' +
                '<button type="button" class="cabinet-sc-attach__chip-remove" data-sc-attach-remove="' + i + '" aria-label="Убрать файл">×</button>' +
                '</span>';
        }).join('');
    }

    document.addEventListener('change', function (e) {
        var input = e.target.closest('[data-sc-attach-input]');
        if (!input) return;
        var wrap = input.closest('[data-sc-attach]');
        if (!wrap) return;
        var files = stateOf(wrap);
        var maxFiles = parseInt(input.getAttribute('data-max-files') || '5', 10) || 5;
        var maxBytes = parseInt(input.getAttribute('data-max-bytes') || '0', 10) || 0;
        var allowed = String(input.getAttribute('accept') || '')
            .split(',')
            .map(function (s) { return s.trim().replace(/^\./, '').toLowerCase(); })
            .filter(Boolean);
        var errors = [];
        Array.prototype.forEach.call(input.files || [], function (file) {
            var ext = String(file.name.split('.').pop() || '').toLowerCase();
            if (allowed.length && allowed.indexOf(ext) === -1) {
                errors.push('Такой тип файла нельзя прикрепить: ' + file.name);
                return;
            }
            if (maxBytes > 0 && file.size > maxBytes) {
                errors.push('Файл ' + file.name + ' больше ' + Math.round(maxBytes / 1024 / 1024) + ' МБ');
                return;
            }
            if (files.length >= maxFiles) {
                errors.push('Максимум ' + maxFiles + ' файлов');
                return;
            }
            files.push(file);
        });
        input.value = '';
        render(wrap);
        if (errors.length) alert(errors.filter(function (v, i, a) { return a.indexOf(v) === i; }).join('\n'));
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-sc-attach-remove]');
        if (!btn) return;
        e.preventDefault();
        var wrap = btn.closest('[data-sc-attach]');
        if (!wrap) return;
        var idx = parseInt(btn.getAttribute('data-sc-attach-remove'), 10);
        stateOf(wrap).splice(idx, 1);
        render(wrap);
    });

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('[data-sc-sub-meta-edit]');
        var cancelBtn = e.target.closest('[data-sc-sub-meta-cancel]');
        var saveBtn = e.target.closest('[data-sc-sub-meta-save]');
        if (!editBtn && !cancelBtn && !saveBtn) return;
        e.preventDefault();
        e.stopPropagation();
        var meta = (editBtn || cancelBtn || saveBtn).closest('[data-sc-sub-meta]');
        var form = meta ? meta.querySelector('[data-sc-sub-meta-form]') : null;
        if (!form) return;

        if (editBtn || cancelBtn) {
            form.hidden = !!cancelBtn || !form.hidden;
            return;
        }

        var url = meta.getAttribute('data-update-url');
        if (!url) return;
        var due = form.querySelector('[data-sc-sub-meta-due]');
        var assignee = form.querySelector('[data-sc-sub-meta-assignee]');
        saveBtn.disabled = true;
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                due_at: due ? due.value : '',
                assignee_user_id: assignee ? assignee.value : '',
            }),
        }).then(function (r) {
            return r.json().catch(function () { return null; }).then(function (data) {
                return { ok: r.ok && data && data.ok, data: data };
            });
        }).then(function (res) {
            saveBtn.disabled = false;
            if (!res.ok) {
                alert((res.data && res.data.message) || 'Error');
                return;
            }
            var html = res.data.item && res.data.item.meta_html;
            if (html) meta.outerHTML = html;
            else form.hidden = true;
        }).catch(function () {
            saveBtn.disabled = false;
        });
    });

    document.addEventListener('click', function (e) {
        var more = e.target.closest('[data-sc-sub-comments-more]');
        if (!more) return;
        e.preventDefault();
        var box = more.closest('[data-sc-sub-comments]');
        if (!box) return;
        var expand = more.getAttribute('aria-expanded') !== 'true';
        box.querySelectorAll('[data-sc-sub-comment-extra]').forEach(function (el) {
            el.hidden = !expand;
        });
        more.setAttribute('aria-expanded', expand ? 'true' : 'false');
        more.textContent = more.getAttribute(expand ? 'data-label-less' : 'data-label-more');
    });

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-sc-sub-comment-toggle]');
        var cancel = e.target.closest('[data-sc-sub-comment-cancel]');
        var save = e.target.closest('[data-sc-sub-comment-save]');
        if (!toggle && !cancel && !save) return;
        e.preventDefault();
        var meta = (toggle || cancel || save).closest('[data-sc-sub-meta]');
        var form = meta ? meta.querySelector('[data-sc-sub-comment-form]') : null;
        if (!form) return;

        if (toggle || cancel) {
            form.hidden = !!cancel || !form.hidden;
            if (!form.hidden) {
                var ta = form.querySelector('[data-sc-sub-comment-body]');
                if (ta) ta.focus();
            }
            return;
        }

        var url = meta.getAttribute('data-note-url');
        var bodyEl = form.querySelector('[data-sc-sub-comment-body]');
        var body = bodyEl ? String(bodyEl.value || '').trim() : '';
        var files = window.cabinetScAttach.files(form);
        if (!url || (!body && !files.length)) return;
        save.disabled = true;
        var payload = files.length ? window.cabinetScAttach.formData({ body: body }, files) : JSON.stringify({ body: body });
        var headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        };
        if (!files.length) headers['Content-Type'] = 'application/json';
        fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: payload })
            .then(function (r) {
                return r.json().catch(function () { return null; }).then(function (data) {
                    return { ok: r.ok && data && data.ok, data: data };
                });
            })
            .then(function (res) {
                save.disabled = false;
                if (!res.ok) {
                    alert((res.data && res.data.message) || 'Error');
                    return;
                }
                if (res.data.meta_html) meta.outerHTML = res.data.meta_html;
            })
            .catch(function () {
                save.disabled = false;
            });
    });

    var lb = null;

    function buildLightbox() {
        var el = document.createElement('div');
        el.className = 'cabinet-sc-lightbox';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.innerHTML =
            '<div class="cabinet-sc-lightbox__top">' +
            '<span class="cabinet-sc-lightbox__counter" data-lb-counter></span>' +
            '<span class="cabinet-sc-lightbox__name" data-lb-name></span>' +
            '<a class="cabinet-sc-lightbox__btn" data-lb-download href="#" aria-label="Скачать">' +
            '<i class="bi bi-download" aria-hidden="true"></i><span>Скачать</span></a>' +
            '<button type="button" class="cabinet-sc-lightbox__btn" data-lb-close aria-label="Закрыть">' +
            '<i class="bi bi-x-lg" aria-hidden="true"></i></button>' +
            '</div>' +
            '<button type="button" class="cabinet-sc-lightbox__nav is-prev" data-lb-prev aria-label="Предыдущая">' +
            '<i class="bi bi-chevron-left" aria-hidden="true"></i></button>' +
            '<div class="cabinet-sc-lightbox__stage" data-lb-stage><img data-lb-img alt=""></div>' +
            '<button type="button" class="cabinet-sc-lightbox__nav is-next" data-lb-next aria-label="Следующая">' +
            '<i class="bi bi-chevron-right" aria-hidden="true"></i></button>';
        document.body.appendChild(el);

        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-lb-prev]')) { step(-1); return; }
            if (e.target.closest('[data-lb-next]')) { step(1); return; }
            if (e.target.closest('[data-lb-close]')) { closeLb(); return; }
            if (e.target === el || e.target.hasAttribute('data-lb-stage')) closeLb();
        });

        var touchX = null;
        el.addEventListener('touchstart', function (e) {
            touchX = e.touches && e.touches[0] ? e.touches[0].clientX : null;
        }, { passive: true });
        el.addEventListener('touchend', function (e) {
            if (touchX === null || !e.changedTouches || !e.changedTouches[0]) return;
            var dx = e.changedTouches[0].clientX - touchX;
            touchX = null;
            if (Math.abs(dx) > 50) step(dx < 0 ? 1 : -1);
        });

        return { el: el, items: [], index: 0 };
    }

    function renderLb() {
        var item = lb.items[lb.index];
        if (!item) return;
        var img = lb.el.querySelector('[data-lb-img]');
        img.src = item.url;
        img.alt = item.name;
        lb.el.querySelector('[data-lb-name]').textContent = item.name;
        lb.el.querySelector('[data-lb-counter]').textContent = lb.items.length > 1
            ? (lb.index + 1) + ' / ' + lb.items.length
            : '';
        var dl = lb.el.querySelector('[data-lb-download]');
        dl.href = item.url + (item.url.indexOf('?') === -1 ? '?' : '&') + 'dl=1';
        var multi = lb.items.length > 1;
        lb.el.querySelector('[data-lb-prev]').hidden = !multi;
        lb.el.querySelector('[data-lb-next]').hidden = !multi;
    }

    function step(delta) {
        if (!lb || lb.items.length < 2) return;
        lb.index = (lb.index + delta + lb.items.length) % lb.items.length;
        renderLb();
    }

    function openLb(items, index) {
        if (!lb) lb = buildLightbox();
        lb.items = items;
        lb.index = index;
        renderLb();
        lb.el.hidden = false;
        document.documentElement.classList.add('cabinet-sc-lightbox-open');
    }

    function closeLb() {
        if (!lb) return;
        lb.el.hidden = true;
        lb.el.querySelector('[data-lb-img]').removeAttribute('src');
        document.documentElement.classList.remove('cabinet-sc-lightbox-open');
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-sc-gallery-img], [data-sc-gallery-open]');
        if (!trigger) return;
        var gallery = trigger.closest('[data-sc-gallery]');
        if (!gallery) return;
        e.preventDefault();
        var links = Array.prototype.slice.call(gallery.querySelectorAll('[data-sc-gallery-img]'));
        var items = links.map(function (a) {
            return { url: a.getAttribute('href'), name: a.getAttribute('data-name') || '' };
        });
        var href = trigger.getAttribute('href');
        var index = 0;
        items.forEach(function (it, i) { if (it.url === href) index = i; });
        if (items.length) openLb(items, index);
    });

    document.addEventListener('keydown', function (e) {
        if (!lb || lb.el.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); closeLb(); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); step(-1); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
    });

    window.cabinetScAttach = {
        files: function (scope) {
            var wrap = scope ? scope.querySelector('[data-sc-attach]') : null;
            return wrap ? stateOf(wrap).slice() : [];
        },
        reset: function (scope) {
            var wrap = scope ? scope.querySelector('[data-sc-attach]') : null;
            if (!wrap) return;
            wrap.__scFiles = [];
            render(wrap);
        },
        formData: function (payload, files) {
            var fd = new FormData();
            Object.keys(payload || {}).forEach(function (key) {
                var v = payload[key];
                fd.append(key, v === null || v === undefined ? '' : v);
            });
            (files || []).forEach(function (f) { fd.append('files[]', f, f.name); });
            return fd;
        },
    };
})();
