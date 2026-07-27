(function ($, window) {
  'use strict';

  function engineOf($root) {
    var v = ($root.find('.js-relevance-search-engine').val() || 'yandex').toLowerCase();
    return v === 'google' ? 'google' : 'yandex';
  }

  function defaultsFor(engine) {
    var cfg = window.cabinetRelevanceSe || {};
    var map = cfg.defaultRegions || {};
    return map[engine] || (engine === 'google'
      ? { id: '1011969', name: 'Москва', text: 'Москва [1011969]' }
      : { id: '213', name: 'Москва', text: 'Москва [213]' });
  }

  function setRegion($select, region) {
    if (!$select.length || !region || !region.id) return;
    $select.find('option').remove();
    var text = region.name || region.text || region.id;
    $select.append(new Option(text, String(region.id), true, true));
    $select.val(String(region.id)).trigger('change.select2');
  }

  function syncHint($root) {
    $root.find('.js-relevance-google-limits-hint').toggleClass('d-none', engineOf($root) !== 'google');
  }

  function checkTypeOf($root) {
    var $type = $root.find('#check-type').first();
    if ($type.length) {
      return String($type.val() || 'phrase');
    }
    $type = $root.find('input#type, input[name="type"], select[name="type"]').first();
    if ($type.length) {
      return String($type.val() || 'phrase');
    }
    return 'phrase';
  }

  function topOf($root) {
    var $count = $root.find('select.count:visible, select[name="count"]:visible').first();
    if (!$count.length) {
      $count = $root.find('select.count, select[name="count"]').first();
    }
    var top = parseInt($count.val(), 10);
    return isNaN(top) || top < 10 ? 10 : top;
  }

  /** Same as RelevanceHistory::serpRequestCost — Yandex=1, Google=ceil(TOP/10), list=1. */
  function serpRequestCost($root) {
    if (checkTypeOf($root) === 'list') {
      return 1;
    }
    if (engineOf($root) !== 'google') {
      return 1;
    }
    return Math.max(1, Math.ceil(topOf($root) / 10));
  }

  /** Non-null only on queue form with #params. */
  function queueRows($root) {
    var $params = $root.find('#params');
    if (!$params.length) {
      return null;
    }
    var n = 0;
    String($params.val() || '').split('\n').forEach(function (line) {
      if (String(line).trim() !== '') {
        n += 1;
      }
    });
    return n;
  }

  function updateLimitCost($root) {
    $root = $root && $root.length ? $root : $(document);
    var per = serpRequestCost($root);
    var rows = queueRows($root);
    var total = rows === null ? per : per * rows;
    $root.find('.js-relevance-limit-cost').text(String(total));
  }

  function initRegionSelect($select, $root) {
    if (!$select.length || typeof $.fn.select2 !== 'function') return;

    if ($select.hasClass('select2-hidden-accessible')) {
      $select.select2('destroy');
    }

    var cfg = window.cabinetRelevanceSe || {};
    var i18n = cfg.i18n || {};

    $select.select2({
      theme: 'bootstrap4',
      placeholder: $select.data('placeholder') || i18n.regionPlaceholder || 'Регион',
      allowClear: false,
      minimumInputLength: 0,
      width: '100%',
      dropdownParent: $(document.body),
      language: {
        inputTooShort: function () { return i18n.regionSearchMin || ''; },
        noResults: function () { return i18n.regionNotFound || ''; },
        searching: function () { return i18n.regionSearching || ''; },
      },
      ajax: {
        delay: 250,
        url: cfg.routes && cfg.routes.regions,
        dataType: 'json',
        data: function (params) {
          return {
            q: params.term || '',
            limit: 25,
            engine: engineOf($root),
          };
        },
        processResults: function (data) {
          return {
            results: $.map(data.results || [], function (item) {
              return { id: item.id, text: item.text, name: item.name };
            }),
          };
        },
      },
    });
  }

  function bind($root) {
    $root = $root && $root.length ? $root : $(document);
    var $engine = $root.find('.js-relevance-search-engine');
    var $region = $root.find('.js-relevance-region');
    if (!$engine.length || !$region.length) return;

    initRegionSelect($region, $root);
    syncHint($root);
    updateLimitCost($root);

    $engine.off('change.relevanceSe').on('change.relevanceSe', function () {
      var engine = engineOf($root);
      setRegion($region, defaultsFor(engine));
      syncHint($root);
      updateLimitCost($root);
    });

    $root
      .off('change.relevanceSeCost')
      .on(
        'change.relevanceSeCost',
        '#check-type, select.count, select[name="count"], input#type, input[name="type"]',
        function () {
          updateLimitCost($root);
        }
      )
      .off('input.relevanceSeCost')
      .on('input.relevanceSeCost', '#params', function () {
        updateLimitCost($root);
      });
  }

  function payloadFields($root) {
    $root = $root && $root.length ? $root : $(document);
    return {
      searchEngine: engineOf($root),
      region: $root.find('.js-relevance-region').val(),
    };
  }

  window.cabinetRelevanceSearchEngine = {
    bind: bind,
    payloadFields: payloadFields,
    serpRequestCost: function ($root) {
      return serpRequestCost($root && $root.length ? $root : $(document));
    },
    updateLimitCost: updateLimitCost,
    engineOf: function ($root) {
      return engineOf($root && $root.length ? $root : $(document));
    },
  };

  $(function () {
    bind($(document));
  });
})(jQuery, window);
