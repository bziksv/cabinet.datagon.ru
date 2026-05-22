<script>
    (function () {
        if (typeof axios !== 'undefined') {
            var token = document.querySelector('meta[name="csrf-token"]');
            if (token) {
                axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
            }
        }

        toastr.options = {timeOut: 1200};

        function copyText(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            return Promise.resolve();
        }

        $(document).on('click', '.copy-item', function () {
            var slug = ($(this).data('copy') || '').toString().toLowerCase().replace(/\s+/g, '-');
            copyText(slug).then(function () {
                toastr.success(@json(__('Copied')));
            });
        });

        function latinName(name) {
            return /^[a-zA-Z\s]+$/.test(name);
        }

        $(document).on('click', '.add-item', function () {
            var type = $(this).data('type');
            var label = type === 'role' ? @json(__('Role name')) : @json(__('Permission name'));
            var name = prompt(label);
            if (!name || !latinName(name)) {
                if (name) {
                    alert(@json(__('Latin letters only')));
                }
                return;
            }
            axios.post(@json(url('/manage-access')), {type: type, name: name})
                .then(function () { window.location.reload(); })
                .catch(function (err) {
                    alert((err.response && err.response.data && err.response.data.message) || err.message);
                });
        });

        $(document).on('click', '.update-item', function () {
            var $btn = $(this);
            var type = $btn.data('type');
            var id = $btn.data('id');
            var current = $btn.data('name');
            var name = prompt(@json(__('New name')), current);
            if (!name || !latinName(name)) {
                if (name) {
                    alert(@json(__('Latin letters only')));
                }
                return;
            }
            axios.patch(@json(url('/manage-access')) + '/' + id, {type: type, name: name})
                .then(function () { window.location.reload(); })
                .catch(function (err) {
                    alert((err.response && err.response.data && err.response.data.message) || err.message);
                });
        });

        $(document).on('click', '.delete-item', function () {
            if (!confirm(@json(__('Are you sure?')))) {
                return;
            }
            var $btn = $(this);
            var type = $btn.data('type');
            var id = $btn.data('id');
            axios.get(@json(url('/manage-access/destroy')) + '/' + id + '/where/' + type)
                .then(function () { window.location.reload(); });
        });

        $(document).on('click', '.revoke-permission', function () {
            var $li = $(this).closest('li');
            var role = $li.closest('.cabinet-ma-role-list').data('role');
            var permission = $li.data('permission');
            axios.post(@json(url('/manage-access/assignPermission')), {
                action: 'revoke',
                role: role,
                permission: permission,
            }).then(function () {
                $li.remove();
            }).catch(function (err) {
                alert((err.response && err.response.data && err.response.data.message) || err.message);
            });
        });

        var $pools = $('.cabinet-ma-permission-pool');
        var $roleLists = $('.cabinet-ma-role-list');

        $pools.sortable({
            connectWith: '.cabinet-ma-role-list',
            handle: '.handle',
            placeholder: 'sort-highlight',
            forcePlaceholderSize: true,
            zIndex: 999999,
            helper: 'clone',
            revert: 'invalid',
            remove: function (event, ui) {
                ui.item.clone().appendTo($(ui.item.parent()));
            },
        });

        $roleLists.sortable({
            items: '> li.cabinet-ma-assigned',
            placeholder: 'sort-highlight',
            forcePlaceholderSize: true,
            receive: function (event, ui) {
                var $item = ui.item;
                $item.addClass('cabinet-ma-assigned');
                $item.find('.handle').remove();
                if (!$item.find('.bi-check-circle-fill').length) {
                    $item.prepend($('<i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>'));
                }
                var tools = $item.find('.tools');
                if (!tools.length) {
                    $item.append(
                        $('<div class="tools"><button type="button" class="revoke-permission" title="' + @json(__('Revoke')) + '">' +
                            '<i class="bi bi-trash"></i></button></div>')
                    );
                } else {
                    tools.html(
                        '<button type="button" class="revoke-permission" title="' + @json(__('Revoke')) + '">' +
                        '<i class="bi bi-trash"></i></button>'
                    );
                }

                var role = $item.closest('.cabinet-ma-role-list').data('role');
                var permission = $item.data('permission');
                if (!permission) {
                    return;
                }

                axios.post(@json(url('/manage-access/assignPermission')), {
                    action: 'assign',
                    role: role,
                    permission: permission,
                }).catch(function (err) {
                    alert((err.response && err.response.data && err.response.data.message) || err.message);
                    window.location.reload();
                });
            },
        });
    })();
</script>
