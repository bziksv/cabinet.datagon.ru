(function () {
    var modalEl = null;
    var listEl = null;
    var noteWrap = null;
    var noteEl = null;
    var saveBtn = null;
    var bsModal = null;
    var pending = null;
    var commentStatuses = { skip: 1, blocked: 1, clarify: 1 };

    function ensure() {
        if (modalEl) return true;
        modalEl = document.getElementById('cabinetScStatusModal');
        if (!modalEl) return false;
        listEl = modalEl.querySelector('[data-sc-status-modal-list]');
        noteWrap = modalEl.querySelector('[data-sc-status-modal-note-wrap]');
        noteEl = modalEl.querySelector('[data-sc-status-modal-note]');
        saveBtn = modalEl.querySelector('[data-sc-status-modal-save]');
        if (window.bootstrap && bootstrap.Modal) {
            bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        if (saveBtn) {
            saveBtn.addEventListener('click', onSave);
        }
        modalEl.addEventListener('hidden.bs.modal', function () {
            pending = null;
            if (noteEl) noteEl.value = '';
        });
        return true;
    }

    function selectedValue() {
        var checked = listEl && listEl.querySelector('input[name="scStatusPick"]:checked');
        return checked ? checked.value : null;
    }

    function syncNoteVisibility() {
        if (!noteWrap) return;
        var value = selectedValue();
        var need = !!(value && commentStatuses[value]);
        noteWrap.classList.toggle('d-none', !need);
    }

    function onSave() {
        if (!pending) return;
        var value = selectedValue();
        if (!value) return;
        var note = noteEl ? String(noteEl.value || '').trim() : '';
        if (commentStatuses[value] && !note) {
            if (noteEl) {
                noteEl.focus();
                noteEl.classList.add('is-invalid');
            }
            return;
        }
        if (noteEl) noteEl.classList.remove('is-invalid');
        var cb = pending.onSelect;
        pending = null;
        if (bsModal) bsModal.hide();
        else modalEl.classList.remove('show');
        if (typeof cb === 'function') {
            cb(value, note);
        }
    }

    function renderOptions(options, defaultValue) {
        if (!listEl) return;
        listEl.innerHTML = '';
        options.forEach(function (opt, idx) {
            var id = 'scStatusPick_' + idx;
            var label = document.createElement('label');
            label.className = 'cabinet-sc-status-modal__option';
            label.setAttribute('for', id);
            var input = document.createElement('input');
            input.type = 'radio';
            input.name = 'scStatusPick';
            input.id = id;
            input.value = opt.value;
            if (opt.value === defaultValue || (!defaultValue && idx === 0)) {
                input.checked = true;
            }
            input.addEventListener('change', syncNoteVisibility);
            var span = document.createElement('span');
            span.textContent = opt.label;
            label.appendChild(input);
            label.appendChild(span);
            listEl.appendChild(label);
        });
        syncNoteVisibility();
    }

    /**
     * @param {{options:Array<{value:string,label:string}>,defaultValue?:string,onSelect:Function}} cfg
     */
    window.cabinetSeoChecklistAskStatus = function (cfg) {
        if (!cfg || !cfg.options || !cfg.options.length || typeof cfg.onSelect !== 'function') {
            return false;
        }
        if (!ensure()) return false;
        pending = cfg;
        var defaultValue = cfg.defaultValue || '';
        if (!defaultValue) {
            for (var i = 0; i < cfg.options.length; i++) {
                if (cfg.options[i].value === 'rework') {
                    defaultValue = 'rework';
                    break;
                }
            }
            if (!defaultValue) defaultValue = cfg.options[0].value;
        }
        renderOptions(cfg.options, defaultValue);
        if (noteEl) {
            noteEl.value = '';
            noteEl.classList.remove('is-invalid');
        }
        if (bsModal) {
            bsModal.show();
        } else {
            modalEl.style.display = 'block';
            modalEl.classList.add('show');
        }
        return true;
    };
})();
