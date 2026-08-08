<?php

/**
 * Публичный демо-кабинет: готовый аккаунт с результатами, без запусков и списаний.
 */
return [
    'enabled' => (bool) env('DEMO_CABINET_ENABLED', true),

    /** Email демо-пользователя (создаётся командой demo-cabinet:seed) */
    'email' => env('DEMO_CABINET_EMAIL', 'demo@cabinet.titlo.ru'),

    /** Пароль только для сидера; вход с сайта — через /demo-cabinet без пароля */
    'password' => env('DEMO_CABINET_PASSWORD', 'DemoCabinet!titlo'),

    'name' => 'Демо',
    'last_name' => 'Кабинет',

    /** Тариф с историей модулей (на Free история = 0) */
    'tariff_role' => 'Maximum',

    /** Email владельца данных для клона витрины (не чужие клиентские проекты) */
    'source_email' => env('DEMO_CABINET_SOURCE_EMAIL', 'sv6@list.ru'),

    /**
     * Предпочтительный проект релевантности у source_email (имя ProjectRelevanceHistory).
     * Если в окне свежести нет — любой свежий проект этого пользователя.
     */
    'relevance_source_project' => env('DEMO_CABINET_RELEVANCE_PROJECT', 'lormag.ru'),

    /**
     * Макс. возраст исходного снимка релевантности (дней).
     * null = брать relevance_analysis_config.cleaning_interval (после него кроном
     * обнуляются облака/unigram — деталка уже пустая). Сейчас в БД обычно 180.
     */
    'relevance_source_max_age_days' => env('DEMO_CABINET_RELEVANCE_MAX_AGE_DAYS'),

    /**
     * Куда вести после входа в демо (/demo-cabinet).
     * Раньше приоритетом был /show-history/{id} — теперь главная.
     */
    'home_path' => env('DEMO_CABINET_HOME_PATH', '/'),

    /**
     * Разрешённые пути для ?to= с маркетинга (префиксы).
     * Пример: /demo-cabinet?to=/monitoring-v2
     */
    'entry_paths' => [
        '/monitoring-v2',
        '/monitoring',
        '/analyze-relevance',
        '/competitor-analysis',
        '/site-monitoring',
        '/meta-tags',
        '/keyword-generator',
        '/counting-text-length',
        '/password-generator',
        '/list-comparison',
        '/duplicates',
        '/utm-marks',
        '/roi-calculator',
        '/http-headers',
        '/index-check',
        '/esenin-text-check',
        '/search-suggestions',
        '/domain-records',
        '/site-types',
        '/phrase-commerce',
        '/html-editor',
        '/unique',
        '/backlink',
        '/domain-information',
        '/text-analyzer',
        '/cluster',
        '/site-audit',
    ],

    /** URL регистрации с маркетинга */
    'register_hint' => 'https://titlo.ru/register/',
];
