<?php

return [
    /*
    |--------------------------------------------------------------------------
    | XML River Driver
    |--------------------------------------------------------------------------
    */

    'url' => 'https://xmlriver.com/wordstat/new/json',

    'user' => env('XML_RIVER_USER', ''),
    'key' => env('XML_RIVER_KEY', ''),
];
