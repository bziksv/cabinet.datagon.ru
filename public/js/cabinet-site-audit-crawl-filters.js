(function () {
  'use strict';

  var STORAGE_KEY = 'cabinet_sa_crawl_filter_pins_v1';

  function storageKey() {
    var form = document.querySelector('[data-sa-filters-customize]');
    var code = form && form.getAttribute('data-sa-filter-code');
    if (code === 'crawl_images') {
      return 'cabinet_sa_crawl_images_filter_pins_v1';
    }
    return STORAGE_KEY;
  }

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function $$(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function loadPins(defaults) {
    try {
      var raw = localStorage.getItem(storageKey());
      if (!raw) return defaults.slice();
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return defaults.slice();
      return parsed.map(String).filter(Boolean);
    } catch (e) {
      return defaults.slice();
    }
  }

  function savePins(keys) {
    try {
      localStorage.setItem(storageKey(), JSON.stringify(keys));
    } catch (e) { /* ignore */ }
  }

  function defaultsFromForm(form) {
    var el = form.querySelector('#sa-filter-defaults-json');
    if (!el) return [];
    try {
      var parsed = JSON.parse(el.textContent || '[]');
      return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (e) {
      return [];
    }
  }

  function reinitSelect2(root) {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var $root = jQuery(root);
    var $gearPanel = $root.find('.cabinet-sa-filters-gear__panel').first();
    $root.find('[data-sa-select2-multi]').each(function () {
      var $el = jQuery(this);
      if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
      }
      var opts = {
        theme: 'bootstrap4',
        width: '100%',
        placeholder: $el.attr('data-placeholder') || 'Выберите…',
        allowClear: true,
        closeOnSelect: false,
        language: {
          noResults: function () { return 'Ничего не найдено'; },
          searching: function () { return 'Поиск…'; }
        }
      };
      if ($gearPanel.length && $el.closest('.cabinet-sa-filters-gear__panel').length) {
        opts.dropdownParent = $gearPanel;
      }
      $el.select2(opts);
    });
  }

  function applyLayout(form, pins) {
    var main = $('[data-sa-filter-main]', form);
    var extra = $('[data-sa-filter-extra]', form);
    var actions = $('[data-sa-filter-actions]', form);
    if (!main || !extra) return;

    var pinSet = {};
    pins.forEach(function (k) { pinSet[k] = true; });

    $$('[data-sa-filter-pin]', form).forEach(function (input) {
      var key = input.getAttribute('data-sa-filter-pin');
      input.checked = !!pinSet[key];
      var wrap = form.querySelector('[data-sa-filter-wrap="' + key + '"]');
      if (!wrap) return;
      if (pinSet[key]) {
        main.insertBefore(wrap, actions || null);
      } else {
        extra.appendChild(wrap);
      }
    });

    reinitSelect2(form);
  }

  function isInsideSelect2(el) {
    if (!el || !el.closest) return false;
    return !!(
      el.closest('.select2-container') ||
      el.closest('.select2-dropdown') ||
      el.closest('.select2-results')
    );
  }

  function closeGearOnOutside(gear) {
    if (!gear) return;

    function closeIfOutside(e) {
      if (!gear.open) return;
      var t = e.target;
      if (!t) return;
      if (gear.contains(t)) return;
      if (isInsideSelect2(t)) return;
      gear.open = false;
    }

    // capture: чтобы поймать клик до того, как Select2/иное съест bubbling
    document.addEventListener('pointerdown', closeIfOutside, true);
    document.addEventListener('keydown', function (e) {
      if (!gear.open) return;
      if (e.key === 'Escape' || e.key === 'Esc') {
        gear.open = false;
      }
    });
  }

  function init() {
    var form = $('[data-sa-filters-customize]');
    if (!form) return;

    var defaults = defaultsFromForm(form);
    if (defaults.length === 0) {
      defaults = $$('[data-sa-filter-wrap]', form)
        .slice(0, 8)
        .map(function (w) { return w.getAttribute('data-sa-filter-wrap'); })
        .filter(Boolean);
    }

    var pins = loadPins(defaults);
    // URL всегда на основном экране
    if (pins.indexOf('url') === -1) pins.unshift('url');
    applyLayout(form, pins);

    closeGearOnOutside($('.cabinet-sa-filters-gear', form));

    form.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.getAttribute || !t.getAttribute('data-sa-filter-pin')) return;
      var key = t.getAttribute('data-sa-filter-pin');
      if (key === 'url' && !t.checked) {
        t.checked = true;
        return;
      }
      var next = $$('[data-sa-filter-pin]', form)
        .filter(function (input) { return input.checked; })
        .map(function (input) { return input.getAttribute('data-sa-filter-pin'); })
        .filter(Boolean);
      if (next.indexOf('url') === -1) next.unshift('url');
      savePins(next);
      applyLayout(form, next);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
