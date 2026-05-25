<?php

return [
    /**
     * Видимая версия модуля «Анализ конкурентов» (badge в nav).
     * Журнал: datagon.ru/docs/cabinet-competitor-analysis-changelog.md
     */
    'version' => '2.7.7',

    /** Расширенный лог прогресса для admin / Super Admin */
    'debug_log' => env('COMPETITOR_ANALYSIS_DEBUG_LOG', true),
    'debug_log_ttl_minutes' => (int) env('COMPETITOR_ANALYSIS_DEBUG_LOG_TTL', 120),
    'debug_log_max_entries' => (int) env('COMPETITOR_ANALYSIS_DEBUG_LOG_MAX', 250),

    /** Таймаут HTTP к XML-провайдерам (сек.) */
    'xml_http_timeout' => (int) env('COMPETITOR_ANALYSIS_XML_TIMEOUT', 12),

    /**
     * XMLStock гибридный режим (коды 210/202 — повтор того же URL).
     * @see https://xmlstock.com/?do=help-yaxml-format
     */
    'xmlstock_hybrid_retry' => env('COMPETITOR_XMLSTOCK_HYBRID_RETRY', true),
    'xmlstock_hybrid_max_attempts' => (int) env('COMPETITOR_XMLSTOCK_HYBRID_MAX', 8),
    'xmlstock_hybrid_sleep_sec' => (int) env('COMPETITOR_XMLSTOCK_HYBRID_SLEEP', 22),
    'xmlstock_hybrid_http_timeout' => (int) env('COMPETITOR_XMLSTOCK_HYBRID_HTTP_TIMEOUT', 30),
    'xmlstock_hybrid_retry_codes' => [202, 210],

    /** Таймауты curl при разборе сайтов конкурентов */
    'site_curl_timeout' => (int) env('COMPETITOR_ANALYSIS_SITE_TIMEOUT', 4),
    'site_curl_connect_timeout' => (int) env('COMPETITOR_ANALYSIS_SITE_CONNECT_TIMEOUT', 3),
    'site_curl_max_attempts' => (int) env('COMPETITOR_ANALYSIS_SITE_MAX_ATTEMPTS', 2),

    /** Параллельная загрузка страниц (curl_multi); иначе — по одной */
    'site_curl_parallel' => env('COMPETITOR_ANALYSIS_SITE_PARALLEL', true),
    'site_curl_concurrency' => (int) env('COMPETITOR_ANALYSIS_SITE_CONCURRENCY', 8),

    /** Обновлять % в БД не чаще чем раз в N уникальных URL (снижает нагрузку на MySQL) */
    'progress_update_every_urls' => (int) env('COMPETITOR_ANALYSIS_PROGRESS_EVERY', 2),

    /**
     * Геозависимость: сравнение топ-URL между регионами (Jaccard).
     * ≥ independent_min — выдача совпадает (геонезависимый запрос); ≤ dependent_max — сильно различается.
     */
    'geo_independent_min_jaccard' => (float) env('COMPETITOR_GEO_INDEPENDENT_MIN_JACCARD', 0.65),
    'geo_dependent_max_jaccard' => (float) env('COMPETITOR_GEO_DEPENDENT_MAX_JACCARD', 0.4),

    /**
     * Домены, не учитываемые в геозависимости (маркетплейсы/агрегаторы).
     * Дополняется списком «агрегаторы» из настроек модуля (competitor_configs.agrigators).
     */
    'geo_exclude_domains_default' => [
        'ozon.ru',
        'wildberries.ru',
        'market.yandex.ru',
        'aliexpress.ru',
        'megamarket.ru',
        'avito.ru',
        'irecommend.ru',
        'otzovik.com',
        'sravni.ru',
        'pulscen.ru',
        'tiu.ru',
        'satom.ru',
        'regmarkets.ru',
        'price.ru',
    ],

    /** Доли шкалы прогресса (сумма ≈ 98, остальное — 100%) */
    'progress_xml_percent' => (int) env('COMPETITOR_PROGRESS_XML_PERCENT', 15),
    'progress_scan_percent' => (int) env('COMPETITOR_PROGRESS_SCAN_PERCENT', 80),
    'progress_post_percent' => (int) env('COMPETITOR_PROGRESS_POST_PERCENT', 3),

    /** Рекомендации: кластеризация запросов по пересечению URL в выдаче */
    'recommendation_min_jaccard' => (float) env('COMPETITOR_RECOMMENDATION_MIN_JACCARD', 0.35),
    'recommendation_min_shared_urls' => (int) env('COMPETITOR_RECOMMENDATION_MIN_SHARED_URLS', 3),
    'recommendation_min_competitor_share' => (float) env('COMPETITOR_RECOMMENDATION_MIN_SHARE', 0.3),
    'recommendation_words_per_tag' => (int) env('COMPETITOR_RECOMMENDATION_WORDS_PER_TAG', 20),
    'recommendation_default_tags' => ['title', 'h1', 'description'],

    /** Локально: job после ответа HTTP (dispatch_now в shutdown), без очереди database */
    'run_job_after_response' => env('COMPETITOR_ANALYSIS_RUN_AFTER_RESPONSE', true),

    /** Лимит PHP на один прогон (должен быть ≤ php-fpm max_execution_time) */
    'job_max_execution_sec' => (int) env('COMPETITOR_ANALYSIS_JOB_MAX_SEC', 1200),

    /** php CLI для фонового queue:work (если run_job_after_response=false) */
    'php_cli' => env('COMPETITOR_ANALYSIS_PHP_CLI', '/opt/homebrew/opt/php@7.4/bin/php'),

    /** Локально: поднять queue:work --once через Symfony Process (устаревший путь) */
    'spawn_queue_worker' => env('COMPETITOR_ANALYSIS_SPAWN_QUEUE_WORKER', false),

    'default_search_engine' => 'yandex',

    /** Допустимые ПС для анализа */
    'search_engines' => ['yandex', 'google'],

    /** Сколько регионов (городов) можно выбрать за один запуск */
    'max_regions' => 5,
];
