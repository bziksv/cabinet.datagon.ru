@php
    $isAllHistory = !empty($isAllHistory) || request()->is('*/all-history');
    $tokenStats = $tokenStats ?? null;
    $tokenLeaderboard = $tokenLeaderboard ?? null;
@endphp

@component('component.card', ['title' => 'История'])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'responsive-core-min'])
    @endslot

    <div class="card">
        <div class="card-header d-flex p-0">
            @include('ai-generation.blocks.nav')
        </div>
        <div class="card-body">
            @include('ai-generation.blocks.wip-admin-notice')

            <div class="d-flex flex-wrap align-items-center mb-3" style="gap:12px">
                <label class="mb-0 d-flex align-items-center" style="gap:8px">
                    <span class="text-muted small">Период</span>
                    <select id="ai-history-period" class="form-control form-control-sm" style="width:auto;min-width:200px">
                        @foreach(\App\Support\AiDeepSeekTokenStats::periodPresets() as $value => $label)
                            <option value="{{ $value }}" @if($value === 'all') selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if(!$isAllHistory)
                <div class="alert alert-light border mb-3" id="ai-history-user-stats" @if(empty($tokenStats)) style="display:none" @endif>
                    <div class="d-flex flex-wrap gap-4 align-items-baseline">
                        <div>
                            <div class="text-muted small">Расход токенов генерации</div>
                            <div class="h4 mb-0" data-stat="tokens">{{ number_format((int) ($tokenStats['tokens'] ?? 0), 0, '', ' ') }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Стоимость (≈ $2 / 1 млн ток.)</div>
                            <div class="h4 mb-0" data-stat="cost">{{ $tokenStats['cost_fmt'] ?? '$0.00' }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Запросов</div>
                            <div class="h5 mb-0" data-stat="requests">{{ number_format((int) ($tokenStats['requests'] ?? 0), 0, '', ' ') }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Вход / выход</div>
                            <div class="h5 mb-0">
                                <span data-stat="prompt_tokens">{{ number_format((int) ($tokenStats['prompt_tokens'] ?? 0), 0, '', ' ') }}</span>
                                /
                                <span data-stat="completion_tokens">{{ number_format((int) ($tokenStats['completion_tokens'] ?? 0), 0, '', ' ') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($isAllHistory)
                <div class="alert alert-light border mb-3" id="ai-history-all-stats">
                    <div class="d-flex flex-wrap gap-4 align-items-baseline mb-3">
                        <div>
                            <div class="text-muted small">Всего токенов (все пользователи)</div>
                            <div class="h4 mb-0" data-stat="tokens">{{ number_format((int) data_get($tokenLeaderboard, 'summary.tokens', 0), 0, '', ' ') }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Стоимость (≈ $2 / 1 млн ток.)</div>
                            <div class="h4 mb-0" data-stat="cost">{{ data_get($tokenLeaderboard, 'summary.cost_fmt', '$0.00') }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Пользователей</div>
                            <div class="h5 mb-0" data-stat="users">{{ number_format((int) data_get($tokenLeaderboard, 'summary.users', 0), 0, '', ' ') }}</div>
                        </div>
                        <div>
                            <div class="text-muted small">Запросов</div>
                            <div class="h5 mb-0" data-stat="requests">{{ number_format((int) data_get($tokenLeaderboard, 'summary.requests', 0), 0, '', ' ') }}</div>
                        </div>
                    </div>
                    <div class="table-responsive" id="ai-history-leaderboard-wrap">
                        @if(!empty($tokenLeaderboard['rows']))
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                <tr>
                                    <th>Пользователь</th>
                                    <th>Токены</th>
                                    <th>Стоимость</th>
                                    <th>Вход</th>
                                    <th>Выход</th>
                                    <th>Запросы</th>
                                    <th>Последний</th>
                                </tr>
                                </thead>
                                <tbody id="ai-history-leaderboard-body">
                                @foreach($tokenLeaderboard['rows'] as $row)
                                    <tr>
                                        <td>
                                            <small>
                                                ID {{ (int) $row['user_id'] }}
                                                @if($row['email'] !== '')
                                                    · {{ $row['email'] }}
                                                @endif
                                                @if($row['name'] !== '')
                                                    <br>{{ $row['name'] }}
                                                @endif
                                            </small>
                                        </td>
                                        <td><strong>{{ number_format((int) $row['tokens'], 0, '', ' ') }}</strong></td>
                                        <td>{{ $row['cost_fmt'] ?? '$0.00' }}</td>
                                        <td>{{ number_format((int) $row['prompt_tokens'], 0, '', ' ') }}</td>
                                        <td>{{ number_format((int) $row['completion_tokens'], 0, '', ' ') }}</td>
                                        <td>{{ number_format((int) $row['requests'], 0, '', ' ') }}</td>
                                        <td>
                                            @if(!empty($row['last_at']))
                                                {{ \Illuminate\Support\Carbon::parse($row['last_at'])->format('d.m.Y H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            @endif

            <table id="history-table" class="table table-bordered table-striped w-100">
                <thead>
                    <tr>
                        <th></th>
                        @if($isAllHistory) <th>Пользователь</th> @endif
                        <th>Использовано токенов</th>
                        <th>Стоимость</th>
                        <th>Источник данных</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'responsive-core-min'])

        <script>
            $(function () {
                const isAllHistory = {{ $isAllHistory ? 'true' : 'false' }};

                let columns = [
                    {
                        data: null,
                        className: 'details-control',
                        orderable: false,
                        defaultContent: 'Показать информацию',
                        render: function() { return '<span style="cursor:pointer; color: #007bff;">Показать информацию</span>'; }
                    }
                ];

                if (isAllHistory) {
                    columns.push({
                        data: 'user_info',
                        render: function(data) {
                            return `<small>ID: ${data.id}<br>Name: ${data.name}<br>Email: ${data.email}</small>`;
                        }
                    });
                }

                columns.push(
                    {
                        data: 'used_tokens_fmt',
                        render: function (data, type, row) {
                            if (type === 'sort' || type === 'type') {
                                return row.used_tokens || 0;
                            }
                            return data || '0';
                        }
                    },
                    {
                        data: 'cost_fmt',
                        render: function (data, type, row) {
                            if (type === 'sort' || type === 'type') {
                                return row.cost_usd || 0;
                            }
                            return data || '$0.00';
                        }
                    },
                    {
                        data: 'source',
                        render: function(data) {
                            return data === 'parse_html' ? 'Парсинг HTML' : 'База данных AI';
                        }
                    },
                    {
                        data: 'status',
                        render: function(data) {
                            if (data === 'completed') return '<span class="badge badge-success">Завершено</span>';
                            if (data === 'failed') return '<span class="badge badge-danger">Не удалось</span>';
                            return '<span class="badge badge-warning">В ожидании</span>';
                        }
                    },
                    { data: 'date' },
                    {
                        data: null,
                        orderable: false,
                        render: function() {
                            return `
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Действия</button>
                                    <div class="dropdown-menu">
                                        <a href="#" class="dropdown-item copy-result">Скопировать результат</a>
                                        <a href="#" class="dropdown-item apply-history-full">Применить конфиг</a>
                                    </div>
                                </div>`;
                        }
                    }
                );

                let table = $('#history-table').DataTable({
                    serverSide: true,
                    processing: true,
                    ajax: {
                        url: "{{ route('ai.generation.history.json') }}",
                        type: "POST",
                        data: function (d) {
                            d._token = "{{ csrf_token() }}";
                            d.scope = isAllHistory ? 'all' : 'user';
                            d.period = $('#ai-history-period').val() || 'all';
                        },
                        dataSrc: function (json) {
                            if (!isAllHistory && json.token_stats) {
                                var s = json.token_stats;
                                var box = $('#ai-history-user-stats');
                                box.show();
                                box.find('[data-stat="tokens"]').text(s.tokens_fmt || '0');
                                box.find('[data-stat="cost"]').text(s.cost_fmt || '$0.00');
                                box.find('[data-stat="requests"]').text(s.requests_fmt || '0');
                                box.find('[data-stat="prompt_tokens"]').text(s.prompt_tokens_fmt || '0');
                                box.find('[data-stat="completion_tokens"]').text(s.completion_tokens_fmt || '0');
                            }
                            if (isAllHistory && json.token_leaderboard) {
                                var lb = json.token_leaderboard;
                                var sum = lb.summary || {};
                                var allBox = $('#ai-history-all-stats');
                                allBox.find('[data-stat="tokens"]').text(sum.tokens_fmt || '0');
                                allBox.find('[data-stat="cost"]').text(sum.cost_fmt || '$0.00');
                                allBox.find('[data-stat="users"]').text(sum.users_fmt || '0');
                                allBox.find('[data-stat="requests"]').text(sum.requests_fmt || '0');
                                var rows = lb.rows || [];
                                var body = $('#ai-history-leaderboard-body');
                                if (body.length) {
                                    if (!rows.length) {
                                        body.html('<tr><td colspan="7" class="text-muted">Нет данных за период</td></tr>');
                                    } else {
                                        var html = '';
                                        rows.forEach(function (row) {
                                            var last = row.last_at ? String(row.last_at).replace(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}).*$/, '$3.$2.$1 $4:$5') : '—';
                                            html += '<tr><td><small>ID ' + row.user_id
                                                + (row.email ? ' · ' + $('<div>').text(row.email).html() : '')
                                                + (row.name ? '<br>' + $('<div>').text(row.name).html() : '')
                                                + '</small></td>'
                                                + '<td><strong>' + (row.tokens || 0).toLocaleString('ru-RU').replace(/,/g, ' ') + '</strong></td>'
                                                + '<td>' + (row.cost_fmt || '$0.00') + '</td>'
                                                + '<td>' + (row.prompt_tokens || 0).toLocaleString('ru-RU').replace(/,/g, ' ') + '</td>'
                                                + '<td>' + (row.completion_tokens || 0).toLocaleString('ru-RU').replace(/,/g, ' ') + '</td>'
                                                + '<td>' + (row.requests || 0).toLocaleString('ru-RU').replace(/,/g, ' ') + '</td>'
                                                + '<td>' + last + '</td></tr>';
                                        });
                                        body.html(html);
                                    }
                                }
                            }
                            return json.data || [];
                        }
                    },
                    columns: columns,
                    order: [[isAllHistory ? 6 : 5, "desc"]],
                    @if(!empty($demoAutoOpen))
                    drawCallback: function () {
                        if (window.__demoAiHistoryOpened) {
                            return;
                        }
                        var first = $('#history-table tbody td.details-control').first();
                        if (first.length) {
                            window.__demoAiHistoryOpened = true;
                            first.trigger('click');
                        }
                    },
                    @endif
                });

                $('#ai-history-period').on('change', function () {
                    table.ajax.reload();
                });

                $('#history-table tbody').on('click', 'td.details-control', function () {
                    let tr = $(this).closest('tr');
                    let row = table.row(tr);
                    let rowData = row.data();

                    if (row.child.isShown()) {
                        row.child.hide();
                        $(this).find('span').text('Показать информацию');
                    } else {
                        let tokLine = '';
                        if (rowData.prompt_tokens || rowData.completion_tokens || rowData.used_tokens) {
                            tokLine = `<div class="mb-2 text-muted small">Токены: вход ${rowData.prompt_tokens || 0}, выход ${rowData.completion_tokens || 0}, всего ${rowData.used_tokens_fmt || rowData.used_tokens || 0}`
                                + (rowData.cost_fmt ? ` · стоимость ${rowData.cost_fmt}` : '')
                                + `</div>`;
                        }
                        row.child(`
                            <div class="p-3 bg-light">
                                ${tokLine}
                                <b>Промпт:</b> <div class="mb-2" style="white-space:pre-wrap">${rowData.prompt}</div>
                                <hr>
                                <b>Результат:</b> <div style="white-space:pre-wrap">${rowData.result}</div>
                            </div>
                        `).show();
                        $(this).find('span').text('Скрыть информацию');
                    }
                });

                $(document).on('click', '.apply-history-full', function(e) {
                    e.preventDefault();
                    let data = table.row($(this).closest('tr')).data();

                    $('#prompt-text').val(data.prompt);
                    $('#category-link').val(data.link);
                    $(`input[name="parsing_method"][value="${data.source}"]`).prop('checked', true);

                    if (window.applyWordsFromHistory) {
                        window.applyWordsFromHistory(data.keywords, data.stopwords);
                    }
                    toastr.success('Конфигурация восстановлена');
                });
            });
        </script>
    @endslot
@endcomponent
