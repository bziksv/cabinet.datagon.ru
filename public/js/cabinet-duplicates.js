/**
 * Удаление дубликатов — клиентская обработка (LTE4).
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'cabinet-duplicates-state';
    var root = document.querySelector('.cabinet-duplicates-page');
    if (!root) {
        return;
    }

    var sourceEl = root.querySelector('#cabinet-dup-source');
    var textEl = root.querySelector('#cabinet-dup-text');
    var sourceCountEl = root.querySelector('[data-dup-source-count]');
    var lineCountEl = root.querySelector('[data-dup-line-count]');
    var kpiRoot = root.querySelector('.cabinet-dup-kpi');
    var kpiBefore = root.querySelector('[data-dup-before]');
    var kpiAfter = root.querySelector('[data-dup-after]');
    var kpiDupRemoved = root.querySelector('[data-dup-dup-removed]');
    var kpiEmptyRemoved = root.querySelector('[data-dup-empty-removed]');
    var startCharsEl = root.querySelector('#cabinet-dup-start-chars');
    var endCharsEl = root.querySelector('#cabinet-dup-end-chars');
    var undoBtn = root.querySelector('[data-dup-undo]');
    var dropZoneEl = root.querySelector('[data-dup-dropzone]');
    var textShellEl = root.querySelector('[data-dup-text-shell]');
    var highlightEl = root.querySelector('[data-dup-highlight]');
    var editStatusEl = root.querySelector('[data-dup-edit-status]');
    var editorEl = root.querySelector('.cabinet-dup-editor');
    var resultPaneEl = root.querySelector('.cabinet-dup-pane--result');
    var configEl = document.getElementById('cabinet-duplicates-config');
    var config = {};
    var undoState = null;
    var saveTimer = null;
    var autoBaseline = null;
    var processBeforeText = null;
    var pulseTimer = null;

    if (!sourceEl || !textEl) {
        return;
    }

    if (configEl && configEl.textContent) {
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            config = {};
        }
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Набор вроде "+-!" / ".!?" — удаляем любой из символов.
     * Строка с буквами/цифрами (тег <li>, префикс) — удаляем целиком как есть.
     */
    function isCharClassNeedle(value) {
        var needle = String(value);
        if (needle === '') {
            return false;
        }
        // Есть буква или цифра → это целый префикс/суффикс (например <li>, sku-).
        if (/[\p{L}\p{N}]/u.test(needle)) {
            return false;
        }
        return true;
    }

    function escapeCharClass(value) {
        // В [] особые: ] \ ^ - и на всякий случай [
        return String(value).replace(/[\]\\^\[\-]/g, '\\$&');
    }

    function removeEdgeNeedle(text, needle, edge) {
        needle = String(needle || '');
        if (!needle) {
            return text;
        }

        var reLine;
        var reWord;
        if (isCharClassNeedle(needle)) {
            var cls = escapeCharClass(needle);
            if (edge === 'start') {
                reLine = new RegExp('^[' + cls + ']+', 'gm');
                reWord = new RegExp('([ \\t])[' + cls + ']+', 'gm');
            } else {
                reLine = new RegExp('[' + cls + ']+$', 'gm');
                reWord = new RegExp('[' + cls + ']+([ \\t])', 'gm');
            }
        } else {
            var lit = escapeRegExp(needle);
            if (edge === 'start') {
                reLine = new RegExp('^(?:' + lit + ')+', 'gm');
                reWord = new RegExp('([ \\t])(?:' + lit + ')+', 'gm');
            } else {
                reLine = new RegExp('(?:' + lit + ')+$', 'gm');
                reWord = new RegExp('(?:' + lit + ')+([ \\t])', 'gm');
            }
        }

        if (edge === 'start') {
            return text.replace(reLine, '').replace(reWord, '$1');
        }
        return text.replace(reLine, '').replace(reWord, '$1');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function flash(message, title) {
        if (window.toastr && typeof window.toastr.success === 'function') {
            window.toastr.success(message, title || '');
            return;
        }
        if (message) {
            window.alert(message);
        }
    }

    function syncHighlightMetrics() {
        if (!highlightEl || !textEl) {
            return;
        }
        var cs = window.getComputedStyle(textEl);
        highlightEl.style.padding = cs.padding;
        highlightEl.style.borderWidth = cs.borderWidth;
        highlightEl.style.borderStyle = 'solid';
        highlightEl.style.borderColor = 'transparent';
        highlightEl.style.fontFamily = cs.fontFamily;
        highlightEl.style.fontSize = cs.fontSize;
        highlightEl.style.fontWeight = cs.fontWeight;
        highlightEl.style.lineHeight = cs.lineHeight;
        highlightEl.style.letterSpacing = cs.letterSpacing;
        highlightEl.style.boxSizing = cs.boxSizing;
    }

    function setEditStatus(mode) {
        if (!editStatusEl) {
            return;
        }
        editStatusEl.classList.remove(
            'd-none',
            'cabinet-dup-edit-status--auto',
            'cabinet-dup-edit-status--manual'
        );
        if (!mode) {
            editStatusEl.hidden = true;
            editStatusEl.classList.add('d-none');
            editStatusEl.textContent = '';
            return;
        }
        editStatusEl.hidden = false;
        if (mode === 'manual') {
            editStatusEl.classList.add('cabinet-dup-edit-status--manual');
            editStatusEl.textContent = config.statusManual || 'Edited manually';
        } else {
            editStatusEl.classList.add('cabinet-dup-edit-status--auto');
            editStatusEl.textContent = config.statusAuto || 'Auto-processed';
        }
    }

    function clearHighlight() {
        autoBaseline = null;
        processBeforeText = null;
        if (textShellEl) {
            textShellEl.classList.remove(
                'cabinet-dup-text-shell--lit',
                'cabinet-dup-text-shell--manual',
                'cabinet-dup-text-shell--pulse'
            );
        }
        if (highlightEl) {
            highlightEl.innerHTML = '';
        }
        setEditStatus(null);
    }

    function buildBeforeCounts(beforeText) {
        var counts = Object.create(null);
        splitLines(beforeText).forEach(function (line) {
            counts[line] = (counts[line] || 0) + 1;
        });
        return counts;
    }

    function classifyAutoMarks(beforeText, afterText) {
        var beforeCounts = buildBeforeCounts(beforeText);
        return splitLines(afterText).map(function (line) {
            if (isBlankLine(line)) {
                return '';
            }
            if ((beforeCounts[line] || 0) > 0) {
                beforeCounts[line] -= 1;
                return 'cabinet-dup-hl-line--kept';
            }
            return 'cabinet-dup-hl-line--auto';
        });
    }

    function classifyCurrentMarks(currentText) {
        if (autoBaseline === null) {
            return [];
        }
        if (currentText === autoBaseline) {
            return classifyAutoMarks(processBeforeText || '', currentText);
        }

        var autoCounts = buildBeforeCounts(autoBaseline);
        var beforeCounts = processBeforeText !== null
            ? buildBeforeCounts(processBeforeText)
            : null;

        return splitLines(currentText).map(function (line) {
            if (isBlankLine(line)) {
                return '';
            }
            if ((autoCounts[line] || 0) > 0) {
                autoCounts[line] -= 1;
                // Строка из авторезультата — сохраняем зелёный.
                if (beforeCounts && (beforeCounts[line] || 0) > 0) {
                    beforeCounts[line] -= 1;
                    return 'cabinet-dup-hl-line--kept';
                }
                return 'cabinet-dup-hl-line--auto';
            }
            return 'cabinet-dup-hl-line--manual';
        });
    }

    function renderHighlight(text, marks) {
        if (!highlightEl || !textShellEl) {
            return;
        }
        if (autoBaseline === null) {
            clearHighlight();
            return;
        }

        syncHighlightMetrics();
        var lines = splitLines(text);
        if (text === '') {
            highlightEl.innerHTML = '';
        } else {
            highlightEl.innerHTML = lines.map(function (line, index) {
                var cls = marks[index] || '';
                var body = line === '' ? '&nbsp;' : escapeHtml(line);
                return '<div class="cabinet-dup-hl-line' + (cls ? ' ' + cls : '') + '">' + body + '</div>';
            }).join('');
        }

        highlightEl.scrollTop = textEl.scrollTop;
        highlightEl.scrollLeft = textEl.scrollLeft;
        textShellEl.classList.add('cabinet-dup-text-shell--lit');

        var hasManual = marks.some(function (mark) {
            return mark === 'cabinet-dup-hl-line--manual';
        });
        textShellEl.classList.toggle('cabinet-dup-text-shell--manual', hasManual);
        setEditStatus(hasManual || text !== autoBaseline ? 'manual' : 'auto');
    }

    function refreshHighlight() {
        if (autoBaseline === null) {
            return;
        }
        renderHighlight(textEl.value, classifyCurrentMarks(textEl.value));
    }

    function scrollToResult() {
        var target = resultPaneEl || textShellEl || textEl || editorEl;
        if (target && typeof target.scrollIntoView === 'function') {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        window.setTimeout(function () {
            if (textEl && typeof textEl.focus === 'function') {
                try {
                    textEl.focus({ preventScroll: true });
                } catch (e) {
                    textEl.focus();
                }
            }
        }, 280);
    }

    function pulseEditor() {
        if (!textShellEl) {
            return;
        }
        textShellEl.classList.remove('cabinet-dup-text-shell--pulse');
        // force reflow so animation can replay
        void textShellEl.offsetWidth;
        textShellEl.classList.add('cabinet-dup-text-shell--pulse');
        if (pulseTimer) {
            window.clearTimeout(pulseTimer);
        }
        pulseTimer = window.setTimeout(function () {
            textShellEl.classList.remove('cabinet-dup-text-shell--pulse');
        }, 1200);
    }

    function splitLines(text) {
        // Нельзя /[\r\n]+/ — иначе пустые строки между контентом исчезают
        // и «убрать пустые» всегда даёт emptyRemoved=0.
        return String(text).replace(/\r\n|\r/g, '\n').split('\n');
    }

    function countAllLines(text) {
        var value = String(text);
        if (value === '') {
            return 0;
        }
        return splitLines(value).length;
    }

    function countNonEmptyLines(text) {
        return splitLines(text).filter(function (line) {
            return line.trim() !== '';
        }).length;
    }

    function isBlankLine(line) {
        return String(line).trim() === '';
    }

    function countBlankLines(text) {
        var value = String(text);
        if (value === '') {
            return 0;
        }
        return splitLines(value).filter(isBlankLine).length;
    }

    function isCaseInsensitiveDedup() {
        var el = root.querySelector('#cabinet-dup-opt-dedup-ci');
        return el && el.checked;
    }

    function isSortEnabled() {
        var el = root.querySelector('#cabinet-dup-opt-sort');
        return el && el.checked;
    }

    function getSelectedOptions() {
        var order = [
            'removeExtraSpace',
            'trim',
            'replaceTabWithSpace',
            'removeEmptyRows',
            'lowerCase',
            'removeDuplicates',
            'replaceUmlaut',
            'removeStartingChars',
            'removeEndingChars',
            'sortAlphabetically',
        ];
        var selected = {};

        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            if (input.checked) {
                selected[input.value] = true;
            }
        });

        return order.filter(function (name) {
            return selected[name];
        });
    }

    function updateLineCount() {
        if (sourceCountEl) {
            sourceCountEl.textContent = String(countNonEmptyLines(sourceEl.value));
        }
        if (lineCountEl) {
            lineCountEl.textContent = String(countNonEmptyLines(textEl.value));
        }
    }

    function updateCharFieldsState() {
        root.querySelectorAll('[data-dup-char-toggle]').forEach(function (input) {
            var targetId = input.getAttribute('data-dup-char-toggle');
            var field = root.querySelector('[data-dup-char-field="' + targetId + '"]');
            var charInput = root.querySelector('#' + targetId);
            if (!field || !charInput) {
                return;
            }
            var enabled = input.checked;
            field.classList.toggle('is-disabled', !enabled);
            charInput.disabled = !enabled;
        });
    }

    function updateUndoButton() {
        if (undoBtn) {
            undoBtn.disabled = !undoState;
        }
    }

    function setKpi(before, after, dupRemoved, emptyRemoved) {
        if (!kpiRoot) {
            return;
        }
        kpiRoot.classList.remove('is-empty');
        if (kpiBefore) {
            kpiBefore.textContent = String(before);
        }
        if (kpiAfter) {
            kpiAfter.textContent = String(after);
        }
        if (kpiDupRemoved) {
            kpiDupRemoved.textContent = String(dupRemoved);
        }
        if (kpiEmptyRemoved) {
            kpiEmptyRemoved.textContent = String(emptyRemoved);
        }
    }

    function resetKpi() {
        if (kpiRoot) {
            kpiRoot.classList.add('is-empty');
        }
        ['—', '—', '—', '—'].forEach(function (mark, i) {
            var el = [kpiBefore, kpiAfter, kpiDupRemoved, kpiEmptyRemoved][i];
            if (el) {
                el.textContent = mark;
            }
        });
    }

    function processors(metrics) {
        return {
            removeExtraSpace: function (text) {
                return text.replace(/ +/gm, ' ');
            },
            trim: function (text) {
                return splitLines(text).map(function (line) {
                    return line.trim();
                }).join('\n');
            },
            replaceTabWithSpace: function (text) {
                return text.replace(/[ \t]/gm, ' ');
            },
            removeEmptyRows: function (text) {
                var lines = splitLines(text);
                var filtered = lines.filter(function (line) {
                    return !isBlankLine(line);
                });
                return filtered.join('\n');
            },
            lowerCase: function (text) {
                return text.toLowerCase();
            },
            removeStartingChars: function (text) {
                var needle = startCharsEl ? startCharsEl.value : '';
                return removeEdgeNeedle(text, needle, 'start');
            },
            removeEndingChars: function (text) {
                var needle = endCharsEl ? endCharsEl.value : '';
                return removeEdgeNeedle(text, needle, 'end');
            },
            removeDuplicates: function (text) {
                var lines = splitLines(text);
                var seen = {};
                var unique = [];
                var caseInsensitive = isCaseInsensitiveDedup();

                lines.forEach(function (line) {
                    // Пустые не считаем дублями — их убирает опция removeEmptyRows.
                    if (isBlankLine(line)) {
                        unique.push(line);
                        return;
                    }
                    var key = caseInsensitive ? line.toLowerCase() : line;
                    if (!Object.prototype.hasOwnProperty.call(seen, key)) {
                        seen[key] = true;
                        unique.push(line);
                    } else {
                        metrics.dupRemoved += 1;
                    }
                });

                return unique.join('\n');
            },
            replaceUmlaut: function (text) {
                return text.replace(/[ёЁ]/g, 'е');
            },
            sortAlphabetically: function (text) {
                if (!isSortEnabled()) {
                    return text;
                }
                var lines = splitLines(text);
                var nonEmpty = [];
                lines.forEach(function (line) {
                    if (!isBlankLine(line)) {
                        nonEmpty.push(line);
                    }
                });
                nonEmpty.sort(function (a, b) {
                    return a.localeCompare(b, 'ru', { sensitivity: 'base' });
                });
                return nonEmpty.join('\n');
            },
        };
    }

    function processText() {
        var beforeText = sourceEl.value;
        if (String(beforeText).trim() === '') {
            flash(
                config.emptySourceText || 'Paste text into the source list first',
                config.fileTitle || ''
            );
            sourceEl.focus();
            return;
        }

        var before = countAllLines(beforeText);
        var blanksBefore = countBlankLines(beforeText);
        var text = beforeText;
        var metrics = { dupRemoved: 0 };
        var ops = processors(metrics);
        var selected = getSelectedOptions();

        undoState = {
            result: textEl.value,
            autoBaseline: autoBaseline,
            processBeforeText: processBeforeText,
        };
        updateUndoButton();

        selected.forEach(function (name) {
            if (typeof ops[name] === 'function') {
                text = ops[name](text);
            }
        });

        // Исходный список не трогаем — всегда можно сравнить «до» и «после».
        textEl.value = text;
        var after = countAllLines(text);
        // Считаем по факту до/после — не зависит от того, какая опция выкинула пустые.
        var emptyRemoved = Math.max(0, blanksBefore - countBlankLines(text));
        processBeforeText = beforeText;
        autoBaseline = text;
        renderHighlight(text, classifyAutoMarks(beforeText, text));
        updateLineCount();
        setKpi(before, after, metrics.dupRemoved, emptyRemoved);
        scheduleSave();
        scrollToResult();
        pulseEditor();
    }

    function undoLast() {
        if (!undoState) {
            return;
        }
        textEl.value = undoState.result || '';
        autoBaseline = undoState.autoBaseline;
        processBeforeText = undoState.processBeforeText;
        undoState = null;
        updateUndoButton();
        updateLineCount();
        if (autoBaseline === null) {
            resetKpi();
            clearHighlight();
        } else {
            refreshHighlight();
        }
        scheduleSave();
    }

    function copyResult() {
        var text = textEl.value;
        if (!text) {
            flash(config.emptyText || 'Nothing to copy', config.copyTitle || 'Copy');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flash(config.copiedText || 'Copied', config.copyTitle || 'Copy');
            }).catch(function () {
                fallbackCopy(text);
            });
            return;
        }

        fallbackCopy(text);
    }

    function fallbackCopy(text) {
        textEl.focus();
        textEl.select();
        try {
            document.execCommand('copy');
            flash(config.copiedText || 'Copied', config.copyTitle || 'Copy');
        } catch (e) {
            flash(config.copyFailedText || 'Copy failed', config.copyTitle || 'Copy');
        }
    }

    function clearForm() {
        sourceEl.value = '';
        textEl.value = '';
        undoState = null;
        updateUndoButton();
        updateLineCount();
        resetKpi();
        clearHighlight();
        scheduleSave();
    }

    function setAllOptions(checked) {
        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            input.checked = checked;
        });
        updateCharFieldsState();
        scheduleSave();
    }

    function resetDefaults() {
        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            input.checked = input.defaultChecked;
        });
        if (startCharsEl) {
            startCharsEl.value = startCharsEl.defaultValue || '';
        }
        if (endCharsEl) {
            endCharsEl.value = endCharsEl.defaultValue || '';
        }
        updateCharFieldsState();
        scheduleSave();
    }

    function applyPreset(preset) {
        var map = {
            'dedup-only': {
                removeExtraSpace: false,
                trim: false,
                replaceTabWithSpace: false,
                removeEmptyRows: false,
                lowerCase: false,
                removeDuplicates: true,
                replaceUmlaut: false,
                removeStartingChars: false,
                removeEndingChars: false,
                sortAlphabetically: false,
                caseInsensitiveDedup: false,
            },
            seo: {
                removeExtraSpace: false,
                trim: true,
                replaceTabWithSpace: false,
                removeEmptyRows: true,
                lowerCase: true,
                removeDuplicates: true,
                replaceUmlaut: true,
                removeStartingChars: false,
                removeEndingChars: false,
                sortAlphabetically: true,
                caseInsensitiveDedup: true,
            },
        };

        var presetMap = map[preset];
        if (!presetMap) {
            return;
        }

        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            if (Object.prototype.hasOwnProperty.call(presetMap, input.value)) {
                input.checked = !!presetMap[input.value];
            }
        });

        var ciEl = root.querySelector('#cabinet-dup-opt-dedup-ci');
        if (ciEl && Object.prototype.hasOwnProperty.call(presetMap, 'caseInsensitiveDedup')) {
            ciEl.checked = !!presetMap.caseInsensitiveDedup;
        }

        updateCharFieldsState();
        scheduleSave();
    }

    function readState() {
        return {
            source: sourceEl.value,
            result: textEl.value,
            // legacy key — чтобы старые вкладки не теряли текст при миграции
            text: sourceEl.value,
            startChars: startCharsEl ? startCharsEl.value : '',
            endChars: endCharsEl ? endCharsEl.value : '',
            options: {},
            caseInsensitiveDedup: isCaseInsensitiveDedup(),
        };
    }

    function collectOptionsState(state) {
        root.querySelectorAll('[data-dup-option]').forEach(function (input) {
            state.options[input.value] = input.checked;
        });
    }

    function scheduleSave() {
        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }
        saveTimer = window.setTimeout(saveState, 400);
    }

    function saveState() {
        try {
            var state = readState();
            collectOptionsState(state);
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            /* ignore quota / private mode */
        }
    }

    function restoreState() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            var state = JSON.parse(raw);
            if (state.source !== undefined) {
                sourceEl.value = state.source;
            } else if (state.text !== undefined) {
                // Старый формат: один textarea — кладём в исходный список.
                sourceEl.value = state.text;
            }
            if (state.result !== undefined) {
                textEl.value = state.result;
            }
            if (startCharsEl && state.startChars !== undefined) {
                startCharsEl.value = state.startChars;
            }
            if (endCharsEl && state.endChars !== undefined) {
                endCharsEl.value = state.endChars;
            }
            if (state.options) {
                root.querySelectorAll('[data-dup-option]').forEach(function (input) {
                    if (Object.prototype.hasOwnProperty.call(state.options, input.value)) {
                        input.checked = !!state.options[input.value];
                    }
                });
            }
            var ciEl = root.querySelector('#cabinet-dup-opt-dedup-ci');
            if (ciEl && state.caseInsensitiveDedup !== undefined) {
                ciEl.checked = !!state.caseInsensitiveDedup;
            }
            if (sourceEl.value !== '' && textEl.value !== '') {
                processBeforeText = sourceEl.value;
                autoBaseline = textEl.value;
                refreshHighlight();
            }
        } catch (e) {
            /* ignore corrupt storage */
        }
    }

    function applyDemoShowcase() {
        var demo = config.demoShowcase;
        if (!demo || !demo.text) {
            return false;
        }
        sourceEl.value = String(demo.text);
        textEl.value = '';
        if (demo.options) {
            root.querySelectorAll('[data-dup-option]').forEach(function (input) {
                if (Object.prototype.hasOwnProperty.call(demo.options, input.value)) {
                    input.checked = !!demo.options[input.value];
                }
            });
        }
        var ciEl = root.querySelector('#cabinet-dup-opt-dedup-ci');
        if (ciEl && demo.caseInsensitiveDedup !== undefined) {
            ciEl.checked = !!demo.caseInsensitiveDedup;
        }
        updateCharFieldsState();
        processText();
        return true;
    }

    function readTextFile(file) {
        if (!file) {
            return;
        }
        var isText = /^text\//.test(file.type || '') || /\.txt$/i.test(file.name || '');
        if (!isText) {
            flash(config.invalidFileText || 'Only .txt files are supported', config.fileTitle || 'File');
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            sourceEl.value = String(reader.result || '');
            undoState = null;
            updateUndoButton();
            updateLineCount();
            resetKpi();
            clearHighlight();
            scheduleSave();
        };
        reader.readAsText(file, 'UTF-8');
    }

    function bindDropZone() {
        if (!dropZoneEl) {
            return;
        }

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropZoneEl.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZoneEl.classList.add('cabinet-dup-dropzone--active');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropZoneEl.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZoneEl.classList.remove('cabinet-dup-dropzone--active');
            });
        });

        dropZoneEl.addEventListener('drop', function (event) {
            var files = event.dataTransfer && event.dataTransfer.files;
            if (files && files[0]) {
                readTextFile(files[0]);
            }
        });
    }

    function initTooltips() {
        if (window.bootstrap && window.bootstrap.Tooltip) {
            root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new window.bootstrap.Tooltip(el);
            });
        }
    }

    var processBtn = root.querySelector('[data-dup-process]');
    if (processBtn) {
        processBtn.addEventListener('click', processText);
    }
    var copyBtn = root.querySelector('[data-dup-copy]');
    if (copyBtn) {
        copyBtn.addEventListener('click', copyResult);
    }
    root.querySelectorAll('[data-dup-clear]').forEach(function (clearBtn) {
        clearBtn.addEventListener('click', clearForm);
    });
    var selectAllBtn = root.querySelector('[data-dup-select-all]');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            setAllOptions(true);
        });
    }
    var deselectAllBtn = root.querySelector('[data-dup-deselect-all]');
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function () {
            setAllOptions(false);
        });
    }
    var resetBtn = root.querySelector('[data-dup-reset-options]');
    if (resetBtn) {
        resetBtn.addEventListener('click', resetDefaults);
    }
    if (undoBtn) {
        undoBtn.addEventListener('click', undoLast);
    }

    root.querySelectorAll('[data-dup-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyPreset(btn.getAttribute('data-dup-preset'));
        });
    });

    sourceEl.addEventListener('input', function () {
        updateLineCount();
        scheduleSave();
    });

    textEl.addEventListener('input', function () {
        updateLineCount();
        refreshHighlight();
        scheduleSave();
    });

    textEl.addEventListener('scroll', function () {
        if (!highlightEl || autoBaseline === null) {
            return;
        }
        highlightEl.scrollTop = textEl.scrollTop;
        highlightEl.scrollLeft = textEl.scrollLeft;
    });

    window.addEventListener('resize', function () {
        if (autoBaseline !== null) {
            syncHighlightMetrics();
            refreshHighlight();
        }
    });

    root.querySelectorAll('[data-dup-char-toggle]').forEach(function (input) {
        input.addEventListener('change', function () {
            updateCharFieldsState();
            scheduleSave();
        });
    });

    root.querySelectorAll('[data-dup-option]').forEach(function (input) {
        input.addEventListener('change', scheduleSave);
    });

    if (startCharsEl) {
        startCharsEl.addEventListener('input', scheduleSave);
    }
    if (endCharsEl) {
        endCharsEl.addEventListener('input', scheduleSave);
    }

    var ciCheckbox = root.querySelector('#cabinet-dup-opt-dedup-ci');
    if (ciCheckbox) {
        ciCheckbox.addEventListener('change', scheduleSave);
    }

    root.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            processText();
        }
        if ((event.ctrlKey || event.metaKey) && event.key === 'z' && undoState) {
            var tag = (event.target && event.target.tagName) || '';
            if (tag === 'TEXTAREA' || tag === 'INPUT') {
                return;
            }
            event.preventDefault();
            undoLast();
        }
    });

    bindDropZone();
    if (!applyDemoShowcase()) {
        restoreState();
    }
    updateLineCount();
    updateCharFieldsState();
    updateUndoButton();
    initTooltips();
})(window, document);
