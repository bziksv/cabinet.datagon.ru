<?php

return [
    'version' => '1.3.5',

    /** Демо на titlo.ru/otslezhivanie-ssylok/ — POST api/demo/otslezhivanie-ssylok/run */
    'demo' => [
        'module' => 'otslezhivanie-ssylok',
        'max_runs_per_day' => 5,
    ],

    /** Очередь для кнопки «Проверить все» (supervisor default). */
    'check_queue' => env('BACKLINK_CHECK_QUEUE', 'default'),

    /**
     * Расписание cron на prod (для подсказок в UI).
     * Фактически: curl GET /api/backlink/scan-links и scan-broken-links.
     */
    'schedule' => [
        'full_scan' => '01:00',
        'broken_scan' => 'hourly',
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
