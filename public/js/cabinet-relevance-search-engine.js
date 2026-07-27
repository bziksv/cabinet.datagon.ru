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

    $engine.off('change.relevanceSe').on('change.relevanceSe', function () {
      var engine = engineOf($root);
      setRegion($region, defaultsFor(engine));
      syncHint($root);
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
    engineOf: function ($root) {
      return engineOf($root && $root.length ? $root : $(document));
    },
  };

  $(function () {
    bind($(document));
  });
})(jQuery, window);
