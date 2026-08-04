<?php

return [
    /*
    | Отдельное OAuth-приложение Яндекса для Вебмастера (не Метрика):
    | свои ClientID/secret — у Яндекса отдельные лимиты на API.
    | Права: webmaster:hostinfo (+ опционально webmaster:verify).
    | https://oauth.yandex.ru/ — redirect на /yandex-webmaster/callback
    */
    'client_id' => env('YANDEX_WEBMASTER_CLIENT_ID', ''),
    'client_secret' => env('YANDEX_WEBMASTER_CLIENT_SECRET', ''),
    'redirect_uri' => env('YANDEX_WEBMASTER_REDIRECT_URI', null),
    'authorize_url' => 'https://oauth.yandex.ru/authorize',
    'token_url' => 'https://oauth.yandex.ru/token',
    'api_base' => 'https://api.webmaster.yandex.net',
    'scope' => env('YANDEX_WEBMASTER_SCOPE', 'webmaster:hostinfo webmaster:verify'),
    'timeout' => 20,
];
