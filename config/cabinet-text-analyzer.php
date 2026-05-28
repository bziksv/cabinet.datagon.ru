<?php

return [
    /**
     * Видимая версия страницы /text-analyzer (badge в шапке карточки).
     * Стабильная база PDF/UI: 6.9s. Дальнейшие правки — +0.1 или суффикс dev.
     * Журнал: datagon.ru/docs/cabinet-text-analyzer-changelog.md
     */
    'version' => '7.0',

    /** Демо на datagon.ru/analiz-teksta/ — POST /api/demo/analiz-teksta/run */
    'demo' => [
        'module_slug' => 'analiz-teksta',
        'max_chars' => 3000,
        'min_chars' => 100,
        'max_runs_per_day' => 5,
        'words_rows' => 10,
        'zipf_rows' => 10,
        'zipf_chart_points' => 12,
        'phrases_rows' => 10,
        'cloud_text_words' => 35,
        'compare_words_rows' => 10,
        'full_max_chars' => 38600,
    ],
];
