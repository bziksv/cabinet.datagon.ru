<?php

return [
    'version' => '1.0.1',
    'endpoint' => env('WORDSTAT_ENDPOINT', 'https://xmlstock.com/wordstat/json/'),
    'http_timeout' => (int) env('WORDSTAT_HTTP_TIMEOUT', 30),
    'rps_default' => 5,
    'queue' => 'wordstat',
    'max_phrase_length' => 400,
    'max_phrase_words' => 7,
    'max_seeds_per_run' => 500,
    'retry_delays' => [10, 30, 90, 90],
];
