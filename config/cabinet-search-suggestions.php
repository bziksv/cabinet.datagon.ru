<?php

return [
    'version' => '1.0.1',

    'max_seeds' => 100,
    'max_depth' => 3,
    'request_timeout' => 12,
    'request_pause_ms' => 80,
    'max_results' => 5000,

    'default_yandex_lr' => '213',
    'default_google_domain' => 'google.ru',
    'default_google_hl' => 'ru',
    'default_google_gl' => 'ru',

    /**
     * Домен Google → страна (gl) и язык (hl) для Suggest API.
     * Городов у Google Suggest нет — только страна/язык.
     */
    'google_domains' => [
        'google.ru' => ['gl' => 'ru', 'hl' => 'ru'],
        'google.com' => ['gl' => 'us', 'hl' => 'en'],
        'google.com.ua' => ['gl' => 'ua', 'hl' => 'uk'],
        'google.by' => ['gl' => 'by', 'hl' => 'be'],
        'google.kz' => ['gl' => 'kz', 'hl' => 'ru'],
        'google.com.tr' => ['gl' => 'tr', 'hl' => 'tr'],
        'google.de' => ['gl' => 'de', 'hl' => 'de'],
        'google.co.uk' => ['gl' => 'gb', 'hl' => 'en'],
        'google.pl' => ['gl' => 'pl', 'hl' => 'pl'],
        'google.fr' => ['gl' => 'fr', 'hl' => 'fr'],
        'google.es' => ['gl' => 'es', 'hl' => 'es'],
        'google.it' => ['gl' => 'it', 'hl' => 'it'],
        'google.com.br' => ['gl' => 'br', 'hl' => 'pt'],
        'google.co.jp' => ['gl' => 'jp', 'hl' => 'ja'],
    ],

    /** Страны для gl (ISO-2). hl — язык подсказок. */
    'google_countries' => [
        'ru' => ['name' => 'Россия', 'hl' => 'ru'],
        'ua' => ['name' => 'Украина', 'hl' => 'uk'],
        'by' => ['name' => 'Беларусь', 'hl' => 'be'],
        'kz' => ['name' => 'Казахстан', 'hl' => 'ru'],
        'uz' => ['name' => 'Узбекистан', 'hl' => 'uz'],
        'us' => ['name' => 'США', 'hl' => 'en'],
        'gb' => ['name' => 'Великобритания', 'hl' => 'en'],
        'de' => ['name' => 'Германия', 'hl' => 'de'],
        'fr' => ['name' => 'Франция', 'hl' => 'fr'],
        'es' => ['name' => 'Испания', 'hl' => 'es'],
        'it' => ['name' => 'Италия', 'hl' => 'it'],
        'pl' => ['name' => 'Польша', 'hl' => 'pl'],
        'tr' => ['name' => 'Турция', 'hl' => 'tr'],
        'br' => ['name' => 'Бразилия', 'hl' => 'pt'],
        'jp' => ['name' => 'Япония', 'hl' => 'ja'],
        'cn' => ['name' => 'Китай', 'hl' => 'zh-CN'],
        'in' => ['name' => 'Индия', 'hl' => 'hi'],
        'ae' => ['name' => 'ОАЭ', 'hl' => 'ar'],
        'il' => ['name' => 'Израиль', 'hl' => 'he'],
        'fi' => ['name' => 'Финляндия', 'hl' => 'fi'],
        'lt' => ['name' => 'Литва', 'hl' => 'lt'],
        'lv' => ['name' => 'Латвия', 'hl' => 'lv'],
        'ee' => ['name' => 'Эстония', 'hl' => 'et'],
        'ge' => ['name' => 'Грузия', 'hl' => 'ka'],
        'am' => ['name' => 'Армения', 'hl' => 'hy'],
        'az' => ['name' => 'Азербайджан', 'hl' => 'az'],
    ],

    'modes' => [
        'phrase' => true,
        'space' => false,
        'en' => false,
        'ru' => false,
        'digits' => false,
    ],

    'presets' => [
        'local' => ['рядом', 'ближайший', 'около', 'рядом со мной', 'в моём городе'],
        'shopping' => ['купить', 'цена', 'стоимость', 'недорого', 'скидка', 'магазин'],
        'questions' => ['как', 'что', 'где', 'когда', 'почему', 'сколько', 'какой'],
        'reviews' => ['отзывы', 'отзывы клиентов', 'отзывы сотрудников'],
    ],
];
