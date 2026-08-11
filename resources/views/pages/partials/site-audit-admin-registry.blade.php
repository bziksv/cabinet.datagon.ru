@php
    $summary = $registry['summary'] ?? [];
    $rows = $registry['rows'] ?? [];
@endphp

<div id="cabinet-sa-admin-registry" class="cabinet-sa-registry mt-4">
    <div class="mb-3">
        <h3 class="h5 mb-1">Пользователи и проверки</h3>
        <p class="text-secondary small mb-0">
            Последние {{ number_format($summary['rows_shown'] ?? 0, 0, '', ' ') }}
            проверок (лимит {{ number_format($summary['rows_limit'] ?? 0, 0, '', ' ') }}).
            Откройте отчёт любого пользователя — доступ только для админов.
        </p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sa-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-sa-registry-kpi__icon text-bg-primary">
                        <i class="bi bi-globe2" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sa-registry-kpi__value">{{ number_format($summary['projects_total'] ?? 0, 0, '', ' ') }}</div>
                    <div class="cabinet-sa-registry-kpi__label">Проекты</div>
                    <div class="cabinet-sa-registry-kpi__meta text-secondary small">
                        Пользователей: {{ number_format($summary['users_with_projects'] ?? 0, 0, '', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sa-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-sa-registry-kpi__icon text-bg-info">
                        <i class="bi bi-activity" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sa-registry-kpi__value">{{ number_format($summary['crawls_7d'] ?? 0, 0, '', ' ') }}</div>
                    <div class="cabinet-sa-registry-kpi__label">Проверок за 7 дней</div>
                    <div class="cabinet-sa-registry-kpi__meta text-secondary small">
                        Сегодня: {{ number_format($summary['crawls_today'] ?? 0, 0, '', ' ') }}
                        · 30 дн.: {{ number_format($summary['crawls_30d'] ?? 0, 0, '', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sa-registry-kpi card h-100 border-0 shadow-sm @if(($summary['crawls_running'] ?? 0) > 0) cabinet-sa-registry-kpi--alert @endif">
                <div class="card-body">
                    <div class="cabinet-sa-registry-kpi__icon text-bg-warning text-dark">
                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sa-registry-kpi__value">{{ number_format($summary['crawls_running'] ?? 0, 0, '', ' ') }}</div>
                    <div class="cabinet-sa-registry-kpi__label">Сейчас в работе</div>
                    <div class="cabinet-sa-registry-kpi__meta text-secondary small">
                        Готово: {{ number_format($summary['crawls_done'] ?? 0, 0, '', ' ') }}
                        · Сбой/стоп: {{ number_format($summary['crawls_failed'] ?? 0, 0, '', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sa-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-sa-registry-kpi__icon text-bg-secondary">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sa-registry-kpi__value">{{ number_format($summary['pages_fetched_7d'] ?? 0, 0, '', ' ') }}</div>
                    <div class="cabinet-sa-registry-kpi__label">Страниц за 7 дней</div>
                    <div class="cabinet-sa-registry-kpi__meta text-secondary small">
                        Всего скачано: {{ number_format($summary['pages_fetched_total'] ?? 0, 0, '', ' ') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @if(count($rows) === 0)
                <div class="alert alert-secondary m-3 mb-0">Пока нет ни одной проверки аудита.</div>
            @else
                <div class="cabinet-sa-datatable cabinet-sa-registry-datatable p-3 pt-2">
                    <table id="cabinet-sa-registry-table"
                           class="table table-sm table-bordered table-striped align-middle cabinet-sa-registry-table w-100 mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Пользователь</th>
                            <th class="text-nowrap">Визит</th>
                            <th>Тариф</th>
                            <th>Домен</th>
                            <th class="text-nowrap">Проверка</th>
                            <th>Статус</th>
                            <th class="text-center">Страниц</th>
                            <th class="text-center">Грубые</th>
                            <th class="text-nowrap">Старт</th>
                            <th class="text-nowrap">Действия</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            @php
                                $st = $row['status'] ?? '';
                                $badge = 'secondary';
                                if ($st === 'done') $badge = 'success';
                                elseif (in_array($st, ['failed', 'cancelled'], true)) $badge = 'danger';
                                elseif (in_array($st, ['fetching', 'discovering', 'aggregating', 'queued', 'queued_wait'], true)) $badge = 'primary';
                            @endphp
                            <tr>
                                <td data-order="{{ $row['email'] }} {{ $row['name'] }}">
                                    <div class="cabinet-sa-registry-user">
                                        <div class="fw-semibold text-break">{{ $row['email'] }}</div>
                                        @if($row['name'])
                                            <div class="text-secondary small">{{ $row['name'] }}</div>
                                        @endif
                                        <div class="text-secondary small">ID {{ $row['user_id'] }}</div>
                                    </div>
                                </td>
                                <td data-order="{{ $row['last_online_sort'] }}" class="text-nowrap small">
                                    @if($row['last_online_at'])
                                        <div>{{ $row['last_online_at'] }}</div>
                                        <div class="text-secondary">{{ $row['last_online_human'] }}</div>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['tariff_sort'] }} {{ $row['tariff_label'] }}">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <span class="badge text-bg-secondary">{{ $row['tariff_label'] }}</span>
                                        @if(!empty($row['telegram']))
                                            <span class="badge text-bg-info" title="Telegram">
                                                <i class="bi bi-telegram" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-break">
                                    <span class="fw-semibold">{{ $row['domain'] }}</span>
                                    <div class="text-secondary small">проект #{{ $row['project_id'] }}</div>
                                </td>
                                <td data-order="{{ $row['crawl_id'] }}" class="text-nowrap">
                                    #{{ $row['crawl_id'] }}
                                </td>
                                <td data-order="{{ $row['status'] }}">
                                    <span class="badge text-bg-{{ $badge }}">{{ $row['status_label'] }}</span>
                                    @if(!empty($row['error']))
                                        <div class="text-danger small mt-1" title="{{ $row['error'] }}">{{ \Illuminate\Support\Str::limit($row['error'], 40) }}</div>
                                    @endif
                                </td>
                                <td class="text-center text-nowrap"
                                    data-order="{{ $row['pages_fetched'] }}">
                                    {{ number_format($row['pages_fetched'], 0, '', ' ') }}
                                    <span class="text-secondary">/</span>
                                    {{ number_format(max($row['pages_limit'], $row['pages_total']), 0, '', ' ') }}
                                </td>
                                <td class="text-center" data-order="{{ $row['critical'] }}">
                                    {{ number_format($row['critical'], 0, '', ' ') }}
                                </td>
                                <td data-order="{{ $row['started_sort'] }}" class="text-nowrap small">
                                    {{ $row['started_at'] ?: '—' }}
                                    @if($row['finished_at'])
                                        <div class="text-secondary">до {{ $row['finished_at'] }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ $row['crawl_url'] }}">Отчёт</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
