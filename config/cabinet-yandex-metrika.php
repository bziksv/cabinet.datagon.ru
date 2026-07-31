<?php

return [
    /*
    | OAuth-приложение Яндекса с правом metrika:read (Management API).
    | Создаётся на https://oauth.yandex.ru/ — redirect на /yandex-metrika/callback
    */
    'client_id' => env('YANDEX_METRIKA_CLIENT_ID', env('YANDEX_CLIENT_ID')),
    'client_secret' => env('YANDEX_METRIKA_CLIENT_SECRET', ''),
    'redirect_uri' => env('YANDEX_METRIKA_REDIRECT_URI', null),
    'authorize_url' => 'https://oauth.yandex.ru/authorize',
    'token_url' => 'https://oauth.yandex.ru/token',
    'api_base' => 'https://api-metrika.yandex.net',
    'scope' => 'metrika:read',
    'timeout' => 20,
];
