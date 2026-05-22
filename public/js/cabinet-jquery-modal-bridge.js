/**
 * jQuery .modal() (Bootstrap 4 API) → Bootstrap 5 Modal.
 * Нужен для monitoring, ai-generation, datatables-responsive, bootstrap-modal-form-templates.
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined' || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }

    var Modal = bootstrap.Modal;

    function toBs5Options(opts) {
        if (!opts || typeof opts !== 'object') {
            return {};
        }
        var config = {};
        if (opts.backdrop !== undefined) {
            config.backdrop = opts.backdrop;
        }
        if (opts.keyboard !== undefined) {
            config.keyboard = opts.keyboard;
        }
        if (opts.focus !== undefined) {
            config.focus = opts.focus;
        }
        return config;
    }

    $.fn.modal = function (option) {
        return this.each(function () {
            var el = this;
            var instance = Modal.getInstance(el);

            if (typeof option === 'string') {
                if (!instance) {
                    instance = Modal.getOrCreateInstance(el);
                }
                if (option === 'show') {
                    instance.show();
                } else if (option === 'hide') {
                    if (instance) {
                        instance.hide();
                    }
                } else if (option === 'toggle') {
                    instance.toggle();
                } else if (option === 'dispose') {
                    if (instance) {
                        instance.dispose();
                    }
                }
                return;
            }

            var config = toBs5Options(option);
            instance = Modal.getOrCreateInstance(el, config);

            if (option && option.show === true) {
                instance.show();
            } else if (option && option.show === false) {
                return;
            } else if (option === undefined) {
                instance.show();
            }
        });
    };
})(window.jQuery);
