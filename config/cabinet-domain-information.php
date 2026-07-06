<?php

return [
    'version' => '1.2.0s',

    'report_export_log_limit' => 100,
    'public_share_ttl_days' => [30, 90, 180, 365, 0],
    'stats_log_per_page' => 25,

    'notifications' => [
        'expiration_alert_days' => 20,
        'default_check_dns' => false,
        'default_check_registration_date' => false,
        'email_enabled' => true,
        'telegram_enabled' => true,
    ],

    'demo' => [
        'module' => 'otslezhivanie-sroka-registratsii-domenov',
        'max_runs_per_day' => 5,
    ],
];
