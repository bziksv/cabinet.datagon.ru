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

    /** Fallback, если ещё нет снимка релевантности (иначе → /show-history/{id}) */
    'home_path' => '/history',

    /** URL регистрации с маркетинга */
    'register_hint' => 'https://titlo.ru/register/',
];
