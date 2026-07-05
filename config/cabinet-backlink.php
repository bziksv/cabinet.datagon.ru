<?php

return [
    'version' => '1.0.5s',

    /** Демо на titlo.ru/otslezhivanie-ssylok/ — POST api/demo/otslezhivanie-ssylok/run */
    'demo' => [
        'module' => 'otslezhivanie-ssylok',
        'max_runs_per_day' => 5,
    ],

    'notifications' => [
        'telegram_enabled' => true,
        /** Лимит проектов в тесте Telegram за один клик (admin) */
        'test_max_per_run' => 10,
    ],
];
