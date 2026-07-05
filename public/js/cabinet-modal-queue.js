/**
 * Очередь Bootstrap-модалок: показываем по одной, следующая — после hidden.bs.modal.
 */
(function (window) {
    'use strict';

    var queue = [];
    var showing = false;
    var domReady = false;

    function showNext() {
        if (showing || !domReady || queue.length === 0) {
            return;
        }

        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }

        var item = queue.shift();
        if (!item || !item.el) {
            showNext();
            return;
        }

        showing = true;
        var modal = bootstrap.Modal.getOrCreateInstance(item.el, {backdrop: true, keyboard: true});

        item.el.addEventListener(
            'hidden.bs.modal',
            function onHide() {
                item.el.removeEventListener('hidden.bs.modal', onHide);
                showing = false;
                showNext();
            },
            {once: true}
        );

        modal.show();
    }

    function onDomReady() {
        domReady = true;
        showNext();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', onDomReady, {once: true});
    } else {
        onDomReady();
    }

    window.CabinetModalQueue = {
        /**
         * @param {HTMLElement} el
         * @param {number} priority больше — раньше в очереди
         */
        enqueue: function (el, priority) {
            if (!el) {
                return;
            }

            queue.push({el: el, priority: priority || 0});
            queue.sort(function (a, b) {
                return b.priority - a.priority;
            });
            showNext();
        },
    };
})(window);
