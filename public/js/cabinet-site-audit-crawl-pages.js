(function () {
  'use strict';

  var STORAGE_KEY = 'cabinet_sa_crawl_pages_cols_v3';
  var LEGACY_KEY = 'cabinet_sa_crawl_pages_cols_v2';

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function $$(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function loadState() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
          return {
            visible: Array.isArray(parsed.visible) ? parsed.visible : null,
            order: Array.isArray(parsed.order) ? parsed.order : null
          };
        }
        if (Array.isArray(parsed)) {
          return { visible: parsed, order: null };
        }
      }
      var legacy = localStorage.getItem(LEGACY_KEY);
      if (legacy) {
        var old = JSON.parse(legacy);
        if (Array.isArray(old)) return { visible: old, order: null };
      }
    } catch (e) { /* ignore */ }
    return null;
  }

  function saveState(visible, order) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        visible: visible,
        order: order
      }));
    } catch (e) { /* ignore */ }
  }

  function clearState() {
    try {
      localStorage.removeItem(STORAGE_KEY);
      localStorage.removeItem(LEGACY_KEY);
    } catch (e) { /* ignore */ }
  }

  function defaultKeys(table) {
    if (window.__SA_CRAWL_PAGES_DEFAULT && Array.isArray(window.__SA_CRAWL_PAGES_DEFAULT)) {
      return window.__SA_CRAWL_PAGES_DEFAULT.slice();
    }
    var attr = table.getAttribute('data-sa-cols-default') || '';
    return attr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
  }

  function presetsMap(table) {
    if (window.__SA_CRAWL_PAGES_PRESETS && typeof window.__SA_CRAWL_PAGES_PRESETS === 'object') {
      return window.__SA_CRAWL_PAGES_PRESETS;
    }
    try {
      return JSON.parse(table.getAttribute('data-sa-cols-presets') || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function catalogOrder(root) {
    return $$('[data-sa-col-order]', root)
      .map(function (el) { return el.getAttribute('data-sa-col-order'); })
      .filter(Boolean);
  }

  function catalogDefaultOrder(root) {
    var attr = root.getAttribute('data-sa-cols-catalog') || '';
    var keys = attr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    return keys.length ? keys : catalogOrder(root);
  }

  function mergeOrder(preferred, catalog) {
    var seen = {};
    var out = [];
    (preferred || []).forEach(function (k) {
      if (!k || seen[k] || catalog.indexOf(k) === -1) return;
      seen[k] = true;
      out.push(k);
    });
    catalog.forEach(function (k) {
      if (seen[k]) return;
      seen[k] = true;
      out.push(k);
    });
    return out;
  }

  function applyVisibility(root, table, keys) {
    var set = {};
    (keys || []).forEach(function (k) { set[k] = true; });
    set.url = true;

    $$('[data-sa-col]', table).forEach(function (el) {
      var key = el.getAttribute('data-sa-col');
      if (!key) return;
      if (set[key]) el.classList.remove('is-col-hidden');
      else el.classList.add('is-col-hidden');
    });

    // table-layout:fixed + width:100% — при куче столбцов задаём min-width под скролл
    var visibleCount = Object.keys(set).length;
    table.style.minWidth = visibleCount > 8 ? (Math.max(100, visibleCount * 9) + 'rem') : '';

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

  function applyOrder(table, order) {
    var headRow = table.querySelector('thead tr');
    if (!headRow) return;
    var bodyRows = $$('tbody tr', table);
    order.forEach(function (key) {
      var th = headRow.querySelector('[data-sa-col="' + key + '"]');
      if (th) headRow.appendChild(th);
      bodyRows.forEach(function (tr) {
        var td = Array.prototype.find.call(tr.children, function (ch) {
          return ch.getAttribute && ch.getAttribute('data-sa-col') === key;
        });
        if (td) tr.appendChild(td);
      });
    });
  }

  function applyOrderList(root, order) {
    var panel = $('[data-sa-cols-order-list]', root);
    if (!panel) return;
    var byKey = {};
    $$('[data-sa-col-order]', panel).forEach(function (el) {
      byKey[el.getAttribute('data-sa-col-order')] = el;
    });
    var hint = panel.querySelector('.cabinet-sa-report-cols__hint');
    var foot = panel.querySelector('[data-sa-cols-foot]');
    order.forEach(function (key) {
      var el = byKey[key];
      if (el) panel.appendChild(el);
    });
    if (hint) panel.insertBefore(hint, panel.firstChild);
    if (foot) panel.appendChild(foot);
  }

  function visibleKeys(root) {
    return $$('[data-sa-col-toggle]', root)
      .filter(function (input) { return input.checked || input.disabled; })
      .map(function (input) { return input.getAttribute('data-sa-col-toggle'); })
      .filter(Boolean);
  }

  function persist(root) {
    saveState(visibleKeys(root), catalogOrder(root));
  }

  function bindDrag(root, table) {
    var panel = $('[data-sa-cols-order-list]', root);
    if (!panel) return;
    var dragEl = null;

    $$('[data-sa-col-order]', panel).forEach(function (el) {
      if (el.getAttribute('draggable') !== 'true') return;

      el.addEventListener('dragstart', function (e) {
        dragEl = el;
        el.classList.add('is-dragging');
        try {
          e.dataTransfer.setData('text/plain', el.getAttribute('data-sa-col-order'));
        } catch (err) { /* ignore */ }
        e.dataTransfer.effectAllowed = 'move';
      });

      el.addEventListener('dragend', function () {
        el.classList.remove('is-dragging');
        dragEl = null;
        applyOrder(table, catalogOrder(root));
        persist(root);
      });

      el.addEventListener('dragover', function (e) {
        if (!dragEl || dragEl === el) return;
        e.preventDefault();
        var rect = el.getBoundingClientRect();
        var before = (e.clientY - rect.top) < rect.height / 2;
        if (before) panel.insertBefore(dragEl, el);
        else panel.insertBefore(dragEl, el.nextSibling);
      });
    });
  }

  function resetToDefault(root, table) {
    clearState();
    var order = catalogDefaultOrder(root);
    var visible = defaultKeys(table).filter(function (k) {
      return order.indexOf(k) !== -1;
    });
    if (visible.indexOf('url') === -1) visible.unshift('url');
    applyOrderList(root, order);
    applyOrder(table, order);
    applyVisibility(root, table, visible);
  }

  function applyPerPageChange(sel) {
    if (!sel) return;
    var url = new URL(window.location.href);
    url.searchParams.set('per_page', sel.value);
    url.searchParams.set('page', '1');
    window.location = url.toString();
  }

  function init() {
    var root = $('[data-sa-crawl-pages-cols]');
    var table = $('[data-sa-crawl-pages-table]');
    if (!root || !table) return;

    var catalog = catalogDefaultOrder(root);
    var state = loadState();
    var order = mergeOrder(state && state.order, catalog);
    var visible = (state && state.visible) || defaultKeys(table);
    visible = visible.filter(function (k) { return catalog.indexOf(k) !== -1; });
    if (visible.indexOf('url') === -1) visible.unshift('url');

    applyOrderList(root, order);
    applyOrder(table, order);
    applyVisibility(root, table, visible);

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
      applyVisibility(root, table, next);
      persist(root);
    });

    root.addEventListener('click', function (e) {
      var resetBtn = e.target.closest('[data-sa-cols-reset]');
      if (resetBtn && root.contains(resetBtn)) {
        e.preventDefault();
        resetToDefault(root, table);
        return;
      }

      var btn = e.target.closest('[data-sa-cols-preset]');
      if (!btn || !root.contains(btn)) return;
      e.preventDefault();
      var presetKey = btn.getAttribute('data-sa-cols-preset');
      var presets = presetsMap(table);
      var cols = (presets[presetKey] || []).slice();
      if (cols.indexOf('url') === -1) cols.unshift('url');
      var order = mergeOrder(cols, catalogDefaultOrder(root));
      applyOrderList(root, order);
      applyOrder(table, order);
      applyVisibility(root, table, cols);
      persist(root);
    });

    bindDrag(root, table);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
