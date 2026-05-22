<div class="card shadow-sm cabinet-ms-clicks mt-1">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-mouse me-1"></i>{{ __('Button clicks') }}
        </h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive cabinet-ms-clicks-table-wrap">
            <table id="actionsTable" class="table table-sm table-striped table-hover mb-0 w-100">
                <thead class="table-light">
                <tr id="empty" class="d-none">
                    <th colspan="20"></th>
                </tr>
                <tr class="cabinet-ms-clicks-filters">
                    <th>
                        <label class="form-label small mb-1" for="email">{{ __('Email') }}</label>
                        <input type="text" class="form-control form-control-sm filter-input" id="email" data-index="0">
                    </th>
                    <th>
                        <label class="form-label small mb-1" for="role">{{ __('Tariff') }}</label>
                        <select name="role" id="role" class="form-select form-select-sm filter-input" data-index="1">
                            <option value="Любой">{{ __('Any') }}</option>
                            <option value="Maximum">Maximum</option>
                            <option value="Ultimate">Ultimate</option>
                            <option value="Optimal">Optimal</option>
                            <option value="Free">Free</option>
                        </select>
                    </th>
                    <th>
                        <label class="form-label small mb-1" for="filter-url">URL</label>
                        <select name="url" id="filter-url" class="form-select form-select-sm filter-input" data-index="2"></select>
                    </th>
                    @php($colIndex = 3)
                    @if(is_array($columns))
                        @foreach($columns as $column)
                            <th></th>
                            @php($colIndex++)
                        @endforeach
                    @endif
                </tr>
                <tr>
                    <th data-index="0">{{ __('User') }}</th>
                    <th data-index="1">{{ __('Roles') }}</th>
                    <th data-index="2">URL</th>
                    @php($colIndex = 3)
                    @if(is_array($columns))
                        @foreach($columns as $column)
                            <th data-index="{{ $colIndex }}">{{ __($column) }}</th>
                            @php($colIndex++)
                        @endforeach
                    @endif
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function () {
        var updatedSelect = false;
        var columnCount = {{ (int) $colIndex }};

        $(function () {
            var columns = [
                {name: 'email', data: 'email'},
                {
                    name: 'roles',
                    data: function (row) {
                        var content = '';
                        $.each(row.roles || [], function (k, role) {
                            content += '<span class="badge text-bg-secondary me-1">' + role.name + '</span>';
                        });
                        return content || '—';
                    },
                },
                {
                    name: 'url',
                    data: function (row) {
                        return '<a href="' + row.url + '" target="_blank" rel="noopener" class="small text-break">' + row.url + '</a>';
                    },
                },
                @if(is_array($columns))
                    @foreach($columns as $column)
                    {
                        name: @json($column),
                        data: function (row) {
                            var key = @json(str_replace(' ', '_', $column));
                            return row[key] !== undefined ? row[key] : 0;
                        },
                    },
                    @endforeach
                @endif
            ];

            var table = $('#actionsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(url('/get-click-actions/' . $id)),
                lengthMenu: [10, 25, 50, 100],
                pageLength: 25,
                columns: columns,
                dom: '<"row align-items-center g-2 px-2 pt-2"<"col-sm-auto"l><"col-sm-auto"B>>rt<"row px-2 pb-2"<"col-sm-auto"i><"col-sm"p>>',
                buttons: ['copy', 'csv', 'excel'],
                language: {
                    lengthMenu: '_MENU_',
                    search: '',
                    searchPlaceholder: @json(__('Search')),
                    paginate: {previous: '‹', next: '›'},
                    emptyTable: @json(__('No records')),
                    processing: '<div class="spinner-border spinner-border-sm text-primary"></div>',
                },
                drawCallback: function () {
                    var timeout;
                    $('.filter-input').off('input.cabinetMsClicks').on('input.cabinetMsClicks', function () {
                        clearTimeout(timeout);
                        var idx = $(this).attr('data-index');
                        timeout = setTimeout(function () {
                            table.column(idx).search($('.filter-input[data-index="' + idx + '"]').val()).draw();
                        }, 400);
                    });

                    for (var i = 4; i <= columnCount; i++) {
                        var sum = 0;
                        $('#actionsTable tbody td:nth-child(' + i + ')').each(function () {
                            var value = parseFloat($(this).text());
                            if (!isNaN(value)) {
                                sum += value;
                            }
                        });
                        var header = $('#actionsTable thead tr:last-child th:nth-child(' + i + ')');
                        if (header.length) {
                            var base = header.text().split(':')[0].trim();
                            header.text(base + ': ' + sum);
                        }
                    }

                    addUrlOptions();
                },
            });

            function addUrlOptions() {
                if (updatedSelect) {
                    return;
                }
                var links = [];
                $('#actionsTable tbody td:nth-child(3) a').each(function () {
                    links.push($(this).text());
                });
                var unique = [...new Set(links)];
                unique.forEach(function (value) {
                    if (value) {
                        $('#filter-url').append('<option value="' + value + '">' + value + '</option>');
                    }
                });
                updatedSelect = true;
            }
        });
    })();
</script>
