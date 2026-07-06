<?php

return [
    'version' => '1.1.0s',

    /** Демо на titlo.ru/otslezhivanie-ssylok/ — POST api/demo/otslezhivanie-ssylok/run */
    'demo' => [
        'module' => 'otslezhivanie-ssylok',
        'max_runs_per_day' => 5,
    ],

    'notifications' => [
        'telegram_enabled' => true,
        'email_enabled' => true,
        'default_notify_telegram' => false,
        'default_notify_email' => false,
        /** Лимит проектов в тесте Telegram за один клик (admin) */
        'test_max_per_run' => 10,
    ],
];
