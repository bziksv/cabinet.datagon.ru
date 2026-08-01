(function () {
    var root = document.querySelector('[data-sc-hub="timesheet"]');
    if (!root) return;

    var searchInput = root.querySelector('[data-sc-timesheet-search]');
    var blocks = root.querySelectorAll('[data-sc-timesheet-block]');
    var expandBtn = root.querySelector('[data-sc-timesheet-expand]');
    var collapseBtn = root.querySelector('[data-sc-timesheet-collapse]');
    var emptyFilter = root.querySelector('[data-sc-timesheet-empty-filter]');

    function normalize(text) {
        return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function applySearch() {
        var q = normalize(searchInput ? searchInput.value : '');
        var visibleBlocks = 0;
        blocks.forEach(function (block) {
            var rows = block.querySelectorAll('[data-sc-timesheet-row]');
            var shown = 0;
            rows.forEach(function (row) {
                var hay = normalize(row.getAttribute('data-search') || row.textContent || '');
                var ok = !q || hay.indexOf(q) !== -1;
                row.classList.toggle('is-hidden-filter', !ok);
                if (ok) shown++;
            });
            var blockOk = shown > 0;
            block.classList.toggle('is-hidden-filter', !blockOk);
            if (blockOk) visibleBlocks++;
        });
        if (emptyFilter) {
            emptyFilter.classList.toggle('d-none', !(q && visibleBlocks === 0));
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applySearch);
    }

    if (expandBtn) {
        expandBtn.addEventListener('click', function () {
            blocks.forEach(function (block) {
                if (block.tagName === 'DETAILS') block.open = true;
            });
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            blocks.forEach(function (block) {
                if (block.tagName === 'DETAILS') block.open = false;
            });
        });
    }
})();
