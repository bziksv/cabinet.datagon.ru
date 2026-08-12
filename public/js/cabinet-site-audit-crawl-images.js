(function () {
  'use strict';

  var STORAGE_KEY = 'cabinet_sa_crawl_images_cols_v2';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function $$(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function loadStored() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function saveStored(keys) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(keys));
    } catch (e) { /* ignore */ }
  }

  function defaultKeys(table) {
    var attr = table.getAttribute('data-sa-cols-default') || '';
    return attr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
  }

  function presetsMap(table) {
    try {
      return JSON.parse(table.getAttribute('data-sa-cols-presets') || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function applyCols(root, table, keys) {
    var set = {};
    (keys || []).forEach(function (k) { set[k] = true; });
    set.url = true;

    $$('[data-sa-col]', table).forEach(function (el) {
      var key = el.getAttribute('data-sa-col');
      if (!key) return;
      if (set[key]) {
        el.classList.remove('is-col-hidden');
      } else {
        el.classList.add('is-col-hidden');
      }
    });

    $$('[data-sa-col-toggle]', root).forEach(function (input) {
      var key = input.getAttribute('data-sa-col-toggle');
      if (!key || input.disabled) return;
      input.checked = !!set[key];
    });

    $$('[data-sa-cols-preset]', root).forEach(function (btn) {
      var presetKey = btn.getAttribute('data-sa-cols-preset');
      var presets = presetsMap(table);
      var cols = presets[presetKey] || [];
      var active = cols.length === keys.length && cols.every(function (c) { return set[c]; });
      btn.classList.toggle('is-active', active);
    });
  }

  function visibleKeys(root) {
    return $$('[data-sa-col-toggle]', root)
      .filter(function (input) { return input.checked || input.disabled; })
      .map(function (input) { return input.getAttribute('data-sa-col-toggle'); })
      .filter(Boolean);
  }

  function applyPerPageChange(sel) {
    if (!sel) return;
    var url = new URL(window.location.href);
    url.searchParams.set('per_page', sel.value);
    url.searchParams.set('page', '1');
    window.location = url.toString();
  }

  function init() {
    var root = $('[data-sa-crawl-images-cols]');
    var table = $('[data-sa-crawl-images-table]');
    if (!root || !table) return;

    var keys = loadStored() || defaultKeys(table);
    applyCols(root, table, keys);

    root.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.getAttribute) return;
      if (t.getAttribute('data-sa-per-page') !== null) {
        applyPerPageChange(t);
        return;
      }
      if (!t.getAttribute('data-sa-col-toggle')) return;
      var next = visibleKeys(root);
      if (next.indexOf('url') === -1) next.unshift('url');
      applyCols(root, table, next);
      saveStored(next);
    });

    root.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-sa-cols-preset]');
      if (!btn || !root.contains(btn)) return;
      e.preventDefault();
      var presetKey = btn.getAttribute('data-sa-cols-preset');
      var presets = presetsMap(table);
      var cols = (presets[presetKey] || []).slice();
      if (cols.indexOf('url') === -1) cols.unshift('url');
      applyCols(root, table, cols);
      saveStored(cols);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
