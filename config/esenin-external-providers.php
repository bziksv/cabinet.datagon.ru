<?php

return [
    'languagetool' => [
        'enabled' => (bool) env('ESENIN_LANGUAGETOOL_ENABLED', false),
        'url' => rtrim((string) env('LANGUAGETOOL_URL', 'http://127.0.0.1:8010'), '/'),
        'language' => (string) env('LANGUAGETOOL_LANGUAGE', 'ru-RU'),
        'timeout' => (int) env('LANGUAGETOOL_TIMEOUT', 20),
        'mother_tongue' => (string) env('LANGUAGETOOL_MOTHER_TONGUE', 'ru-RU'),
    ],

    'turgenev' => [
        'enabled' => (bool) env('ESENIN_TURGENEV_ENABLED', false),
        'url' => (string) env('TURGENEV_API_URL', 'https://turgenev.ashmanov.com/'),
        'key' => (string) env('TURGENEV_API_KEY', ''),
        'api' => (string) env('TURGENEV_API_MODE', 'risk'),
        'more' => (int) env('TURGENEV_API_MORE', 1),
        'timeout' => (int) env('TURGENEV_API_TIMEOUT', 30),
        /** Подмешивать баллы Тургенева в локальные блоки (0–100%). */
        'score_blend_percent' => (int) env('TURGENEV_SCORE_BLEND_PERCENT', 50),
    ],

    'opencorpora' => [
        'enabled' => (bool) env('ESENIN_OPENCORPORA_ENABLED', true),
        'url' => (string) env('OPENCORPORA_API_URL', 'https://opencorpora.org/api.php'),
        'timeout' => (int) env('OPENCORPORA_TIMEOUT', 10),
    ],

    'learning' => [
        'enabled' => (bool) env('ESENIN_STYLE_LEARNING_ENABLED', true),
        'storage_path' => storage_path('app/esenin/style-candidates.json'),
        'min_turgenev_param_score' => 1,
        /** Подтягивать HTML-отчёты ?t=s... / ?t=r... после API-проверки (queue). */
        'report_fetch_enabled' => (bool) env('ESENIN_TURGENEV_REPORT_FETCH', true),
        'report_blocks' => ['style', 'readability'],
        'report_base_url' => (string) env('TURGENEV_API_URL', 'https://turgenev.ashmanov.com/'),
        'report_timeout' => (int) env('ESENIN_TURGENEV_REPORT_TIMEOUT', 25),
    ],
];
