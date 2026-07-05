<?php

/**
 * Мониторинг позиций — UI v2 (/monitoring-v2).
 *
 * @see App\Http\Controllers\MonitoringV2Controller
 */
return [
    'version' => '3.5.156-dev',

    /**
     * «Зависшие расписания» (/monitoring/admin): владелец не заходил N дней, но auto_update включён.
     * @see App\Support\MonitoringStaleScheduleReport
     */
    'stale_schedules_inactive_days' => (int) env('CABINET_MONITORING_STALE_SCHEDULES_DAYS', env('CABINET_USERS_STALE_MONITORING_DAYS', 90)),

    /** Free: только ручное снятие; auto_update не ставится и не выполняется cron. */
    'free_tariff_manual_only' => true,

    /** «Напомнить через N недель» для модалки расписания на Free. */
    'schedule_prompt_snooze_weeks' => 2,

    /** Free: retention monitoring_positions (дней); 0 — выкл. Переопределяется monitoring_settings. */
    'free_tariff_positions_retention_days' => (int) env('MONITORING_FREE_POSITIONS_RETENTION_DAYS', 365),

    /** Подбор конкурентов из таблицы («Подобрать из топ-10»): сколько доменов предложить. */
    'competitors_suggest_limit' => 10,

    /** Дополнительно к `monitoring_settings.ignored_domains` — всегда исключаются из подбора. */
    'competitors_ignored_domains' => [
        'yandex.ru',
    ],

    /** Детальные метрики (ТОП-%, средняя) — сколько доменов за один AJAX-запрос. */
    'competitors_stats_batch_size' => 50,

    /** Сравнение позиций (/competitors/positions): запросов в одном AJAX-батче. */
    'competitors_positions_batch_size' => 250,

    /** Сравнение позиций: параллельных AJAX-запросов к visibility (fallback, если bulk недоступен). */
    'competitors_positions_parallel' => 5,

    /** Сравнение позиций: один POST со всеми ключами, батчи только на сервере. */
    'competitors_positions_use_bulk' => true,

    /** Внутренний размер батча в bulk-режиме (запросов к search_indices за проход). */
    'competitors_positions_bulk_chunk' => 1000,

    /** Глубина SERP на один ключ при выборке из search_indices (строк на запрос). */
    'competitors_positions_serp_depth' => 100,

    /** Динамика по датам: bulk по дням снимка (true) или старый fan-out helper×дата×chunk (false). */
    'competitors_changes_dates_use_bulk' => true,

    /** Снимков в периоде — порог «большого» отчёта (confirm + предупреждение). */
    'competitors_changes_dates_large_snapshots' => 15,

    /** Минут без обновления progress — считать задачу зависшей в UI. */
    'competitors_changes_dates_stale_minutes' => 20,

    /** Готовые/ошибочные отчёты dynamics (monitoring_changes_date): хранить N дней; 0 — не удалять. Переопределяется monitoring_settings. */
    'competitors_changes_dates_retention_days' => (int) env('MONITORING_COMPETITORS_DYNAMICS_RETENTION_DAYS', 180),

    /** Оценка секунд на один снимок (для ETA в UI). */
    'competitors_changes_dates_seconds_per_snapshot' => 25,

    /** Сроки публичной ссылки (дни; 0 — бессрочно). */
    'public_share_ttl_days' => [30, 90, 180, 365, 0],

    /** После скольких часов считать локальный/серверный снимок тренда устаревшим (кнопка «Пересчитать»). */
    'trend_stale_hours' => (int) env('MONITORING_TREND_STALE_HOURS', 24),

    'debug_log' => env('MONITORING_V2_DEBUG_LOG', true),
    'debug_log_ttl_minutes' => (int) env('MONITORING_V2_DEBUG_LOG_TTL', 120),
    'debug_log_max_entries' => (int) env('MONITORING_V2_DEBUG_LOG_MAX', 250),
];
