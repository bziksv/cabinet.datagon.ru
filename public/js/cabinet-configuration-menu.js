/**
 * Настройка порядка пунктов меню (/configuration-menu).
 * Совместим с разметкой positions/partials/menu-configuration-tree.blade.php
 *
 * Вложенные ol.for-nest должны быть прямыми потомками li (как в v1) — иначе jquery-sortable не находит контейнеры.
 */
(function ($) {
    'use strict';

    function groupDomId(name) {
        var id = (name || '').replace(/[^a-zA-Z\u0400-\u04FF]/g, '');
        return id !== '' ? id : 'group';
    }

    function initMenuConfiguration(root) {
        var $root = $(root);
        if (!$root.length || $root.data('menuConfigInit')) {
            return;
        }
        $root.data('menuConfigInit', true);

        var saveUrl = $root.data('saveUrl');
        var restoreUrl = $root.data('restoreUrl');
        var csrf = $root.data('csrf');
        var msg = {
            success: $root.data('msgSuccess'),
            restoreSuccess: $root.data('msgRestoreSuccess'),
            saving: $root.data('msgSaving'),
            restoring: $root.data('msgRestoring'),
            error: $root.data('msgError'),
            emptyGroup: $root.data('msgEmptyGroup'),
            duplicateGroup: $root.data('msgDuplicateGroup'),
            groupExists: $root.data('msgGroupExists'),
        };

        var $tree = $root.find('.nested_with_switch.vertical');
        var groupBlock;
        var oldContainer;

        /** Элементы модалок и кнопок — только внутри страницы настройки меню */
        function $page(sel) {
            var $hit = $root.find(sel);
            return $hit.length ? $hit : $(sel);
        }

        var groupSortable = $tree.sortable({
            handle: '.ui-sortable-handle, [data-group-drag-handle]',
            distance: 5,
            afterMove: function (placeholder, container) {
                if (oldContainer !== container) {
                    if (oldContainer) {
                        oldContainer.el.removeClass('active');
                    }
                    container.el.addClass('active');
                    oldContainer = container;
                }
            },
            onDrop: function ($item, container, _super) {
                container.el.removeClass('active');
                _super($item, container);
            },
            isValidTarget: function ($item, container) {
                var $li = $item.closest('li');
                if ($li.attr('data-action') === 'dir') {
                    return !container.el.hasClass('for-nest');
                }
                return true;
            },
        });

        function refreshSortable() {
            $tree.sortable('refresh');
        }

        function showBootstrapToast(type, message) {
            var selector = type === 'error' ? '.toast-error' : '.toast-success';
            var $toast = $root.find(selector);
            if (!$toast.length) {
                return;
            }
            $toast.find('.toast-body').html(message);
            if (window.bootstrap && bootstrap.Toast) {
                var instance = bootstrap.Toast.getOrCreateInstance($toast[0], { delay: 5000 });
                instance.show();
                return;
            }
            $toast.addClass('show');
            setTimeout(function () {
                $toast.removeClass('show');
            }, 5000);
        }

        /** Один канал уведомления: toastr, иначе Bootstrap-toast. Баннер в сайдбаре — всегда. */
        function notifySuccess(message) {
            setStatusBanner('success', message);
            if (window.toastr) {
                toastr.clear();
                toastr.success(message);
                return;
            }
            showBootstrapToast('success', message);
        }

        function notifyError(message) {
            setStatusBanner('error', message);
            if (window.toastr) {
                toastr.clear();
                toastr.error(message);
                return;
            }
            showBootstrapToast('error', message);
        }

        function setStatusBanner(kind, text) {
            var $box = $page('#menuConfigStatus');
            if (!$box.length) {
                return;
            }
            $box.removeClass('d-none alert-secondary alert-success alert-danger alert-info');
            if (kind === 'success') {
                $box.addClass('alert-success');
            } else if (kind === 'error') {
                $box.addClass('alert-danger');
            } else if (kind === 'loading') {
                $box.addClass('alert-info');
            } else {
                $box.addClass('alert-secondary');
            }
            $box.html(text);
        }

        function setSaveButtonState(state) {
            var $btn = $page('#saveChanges');
            if (!$btn.length) {
                return;
            }
            $btn.prop('disabled', state === 'loading');
            $btn.find('.cabinet-menu-config-btn__idle').toggleClass('d-none', state !== 'idle');
            $btn.find('.cabinet-menu-config-btn__busy').toggleClass('d-none', state !== 'loading');
            $btn.find('.cabinet-menu-config-btn__done').toggleClass('d-none', state !== 'done');
            if (state === 'done') {
                $btn.removeClass('btn-success').addClass('btn-outline-success');
                setTimeout(function () {
                    $btn.removeClass('btn-outline-success').addClass('btn-success');
                    setSaveButtonState('idle');
                }, 2500);
            }
        }

        function setRestoreButtonState(state) {
            var $btn = $page('#restore');
            if (!$btn.length) {
                return;
            }
            $btn.prop('disabled', state === 'loading');
            $btn.find('.cabinet-menu-config-restore__idle').toggleClass('d-none', state !== 'idle');
            $btn.find('.cabinet-menu-config-restore__busy').toggleClass('d-none', state !== 'loading');
        }

        function issetItem(name) {
            var exists = false;
            $tree.children('li').each(function () {
                if ($(this).attr('data-name') === name) {
                    exists = true;
                }
            });
            return exists;
        }

        function toggleEyeIcon($btn) {
            var show = $btn.attr('data-action') === 'true';
            var $icon = $btn.find('i');
            $icon.removeClass('bi-eye bi-eye-slash');
            $icon.addClass(show ? 'bi-eye' : 'bi-eye-slash');
        }

        function setGroupPanelOpen($li, open) {
            var $nest = $li.find('.for-nest').first();
            var $panelBtn = $li.find('[data-menu-panel-toggle]').first();
            if (open) {
                $nest.addClass('show');
                $panelBtn.attr('aria-expanded', 'true');
            } else {
                $nest.removeClass('show');
                $panelBtn.attr('aria-expanded', 'false');
            }
        }

        function toggleGroupPanel($btn) {
            var $li = $btn.closest('li[data-action="dir"]');
            var $nest = $li.find('.for-nest').first();
            setGroupPanelOpen($li, !$nest.hasClass('show'));
        }

        function configurationJson() {
            var items = [];
            $tree.children('li').each(function () {
                var action = $(this).attr('data-action');
                if (action === undefined) {
                    items.push({
                        id: $(this).attr('data-id'),
                        name: $(this).attr('data-name'),
                    });
                } else {
                    var dir = [];
                    var $head = $(this).find('.card-header').first();
                    var show = $head.find('[data-menu-sidebar-toggle]').attr('data-action');
                    dir.push({
                        dirName: $(this).attr('data-name'),
                        dir: true,
                        show: show,
                    });
                    $(this).find('.for-nest').first().children('li').each(function () {
                        dir.push({
                            id: $(this).attr('data-id'),
                            name: $(this).attr('data-name'),
                        });
                    });
                    items.push(dir);
                }
            });
            return items;
        }

        function saveChanges() {
            setSaveButtonState('loading');
            setStatusBanner('loading', msg.saving);

            $.ajax({
                type: 'POST',
                url: saveUrl,
                data: {
                    _token: csrf,
                    menuItems: JSON.stringify(configurationJson()),
                },
                success: function () {
                    setSaveButtonState('done');
                    notifySuccess(msg.success);
                },
                error: function () {
                    setSaveButtonState('idle');
                    notifyError(msg.error);
                },
            });
        }

        function refreshBindings() {
            $root.find('.edit-dir-name').off('click.menuconfig').on('click.menuconfig', function () {
                var $wrap = $(this).closest('.card-header');
                $wrap.find('.cabinet-menu-config-group__rename').addClass('is-open');
            });

            $root.find('[data-group-rename-cancel]').off('click.menuconfig').on('click.menuconfig', function () {
                $(this).closest('.cabinet-menu-config-group__rename').removeClass('is-open');
            });

            $root.find('.change-group-name').off('click.menuconfig').on('click.menuconfig', function () {
                var $rename = $(this).closest('.cabinet-menu-config-group__rename');
                var val = $rename.find('[data-group-rename-input]').val().trim();
                if (val === '') {
                    notifyError(msg.emptyGroup);
                    return;
                }
                if (issetItem(val) && $(this).closest('li').attr('data-name') !== val) {
                    notifyError(msg.groupExists);
                    return;
                }
                var $li = $(this).closest('li[data-action="dir"]');
                $li.attr('data-name', val);
                $li.find('[data-group-drag-handle]').text(val);
                $rename.removeClass('is-open');
                saveChanges();
            });

            $root.find('.remove-dir').off('click.menuconfig').on('click.menuconfig', function () {
                groupBlock = $(this).closest('li[data-action="dir"]');
            });

            $root.find('[data-menu-panel-toggle]').off('click.menuconfig').on('click.menuconfig', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleGroupPanel($(this));
            });

            $root.find('[data-menu-sidebar-toggle]').off('click.menuconfig').on('click.menuconfig', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var next = $btn.attr('data-action') === 'true' ? 'false' : 'true';
                $btn.attr('data-action', next);
                toggleEyeIcon($btn);
            });
        }

        function appendGroupHtml(name) {
            var gid = groupDomId(name);
            var esc = $('<div>').text(name).html();
            var showTip = $root.data('tipGroupExpand') || '';
            var html =
                '<li data-name="' + esc + '" class="cabinet-menu-config-group-wrap" data-action="dir">' +
                '  <div class="card cabinet-menu-config-group shadow-sm mb-0">' +
                '    <div class="card-header d-flex flex-wrap align-items-start gap-2">' +
                '      <div class="flex-grow-1 min-w-0">' +
                '        <div class="cabinet-menu-config-group__title text-truncate" data-group-drag-handle title="' +
                ($root.data('tipDragGroup') || '') +
                '">' +
                esc +
                '</div>' +
                '        <div class="cabinet-menu-config-group__rename">' +
                '          <input type="text" class="form-control form-control-sm" value="' +
                esc +
                '" data-group-rename-input>' +
                '          <button type="button" class="btn btn-sm btn-primary change-group-name">' +
                ($root.data('labelChange') || 'Change') +
                '</button>' +
                '          <button type="button" class="btn btn-sm btn-outline-secondary" data-group-rename-cancel>' +
                ($root.data('labelCancel') || 'Cancel') +
                '</button>' +
                '        </div>' +
                '      </div>' +
                '      <div class="cabinet-menu-config-group__tools btn-group btn-group-sm flex-shrink-0">' +
                '        <button type="button" class="btn btn-outline-secondary" data-menu-panel-toggle aria-expanded="true" title="' +
                ($root.data('tipPanelToggle') || '') +
                '"><i class="bi bi-chevron-down cabinet-menu-config-chevron"></i></button>' +
                '        <button type="button" class="btn btn-outline-secondary" data-menu-sidebar-toggle data-action="true" title="' +
                showTip +
                '"><i class="bi bi-eye"></i></button>' +
                '        <button type="button" class="btn btn-outline-secondary edit-dir-name"><i class="bi bi-pencil"></i></button>' +
                '        <button type="button" class="btn btn-outline-danger remove-dir" data-bs-toggle="modal" data-bs-target="#removeModal"><i class="bi bi-trash"></i></button>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '  <ol class="for-nest show cabinet-menu-config-group__list" id="' +
                gid +
                '" data-empty-hint="' +
                ($root.data('emptyHint') || '') +
                '"></ol>' +
                '</li>';
            $tree.prepend(html);
            refreshBindings();
            refreshSortable();
        }

        function createGroupFromModal() {
            var $input = $page('#dir');
            var name = ($input.val() || '').trim();
            if (name === '') {
                notifyError(msg.emptyGroup);
                return false;
            }
            if (issetItem(name)) {
                notifyError(msg.duplicateGroup);
                return false;
            }
            appendGroupHtml(name);
            $input.val('');
            return true;
        }

        $page('#createDirectory').on('click', function () {
            createGroupFromModal();
        });

        $page('#addNewDir').on('shown.bs.modal', function () {
            $page('#dir').trigger('focus');
        });

        $page('#dir').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (createGroupFromModal()) {
                    var modalEl = $page('#addNewDir')[0];
                    if (modalEl && window.bootstrap && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    } else {
                        $page('#addNewDir').modal('hide');
                    }
                }
            }
        });

        $page('#saveChanges').on('click', saveChanges);

        $page('#removeSelectedBlock').on('click', function () {
            if (!groupBlock || !groupBlock.length) {
                return;
            }
            groupBlock.find('.for-nest > li').each(function () {
                $tree.prepend($(this));
            });
            groupBlock.remove();
            refreshSortable();
            saveChanges();
        });

        $page('#restore').on('click', function () {
            setRestoreButtonState('loading');
            setStatusBanner('loading', msg.restoring);

            $.ajax({
                type: 'POST',
                url: restoreUrl,
                data: { _token: csrf },
                success: function () {
                    try {
                        sessionStorage.setItem('cabinet_menu_config_restored', '1');
                    } catch (e) {
                        /* ignore */
                    }
                    window.location.reload();
                },
                error: function () {
                    setRestoreButtonState('idle');
                    notifyError(msg.error);
                },
            });
        });

        try {
            if (sessionStorage.getItem('cabinet_menu_config_restored') === '1') {
                sessionStorage.removeItem('cabinet_menu_config_restored');
                setStatusBanner('success', msg.restoreSuccess);
            }
        } catch (e) {
            /* ignore */
        }

        refreshBindings();
        return { saveChanges: saveChanges, groupSortable: groupSortable };
    }

    $(function () {
        $('#cabinetMenuConfigRoot').each(function () {
            initMenuConfiguration(this);
        });
    });
})(jQuery);
