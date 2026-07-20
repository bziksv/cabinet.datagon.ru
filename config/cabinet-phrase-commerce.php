<?php

/**
 * Проверка фраз: геозависимость, локализация, коммерциализация.
 * Аналог Arsenkin commerce; лимиты как у типов сайтов.
 */
return [
    'version' => '1.4.0',

    'max_phrases' => 200,
    /** Глубина ТОПа для всех метрик */
    'depth' => 20,
    'default_yandex_lr' => '213',
    'default_google_lr' => '1011969',
    /** Пауза между XML-запросами (мс) */
    'request_pause_ms' => 50,

    /**
     * Топ-10 городов РФ по населению — пул контрольных регионов для геозависимости.
     * Контраст выбирается случайно из списка, исключая выбранный пользователем город.
     * google: первый id — основной в UI; остальные — алиасы того же города.
     */
    'top_rf_cities' => [
        ['slug' => 'moscow', 'name' => 'Москва', 'yandex' => '213', 'google' => ['1011969', '1015930']],
        ['slug' => 'spb', 'name' => 'Санкт-Петербург', 'yandex' => '2', 'google' => ['1012040']],
        ['slug' => 'novosibirsk', 'name' => 'Новосибирск', 'yandex' => '65', 'google' => ['1011984']],
        ['slug' => 'ekaterinburg', 'name' => 'Екатеринбург', 'yandex' => '54', 'google' => ['1012052']],
        ['slug' => 'kazan', 'name' => 'Казань', 'yandex' => '43', 'google' => ['1012054']],
        ['slug' => 'nnovgorod', 'name' => 'Нижний Новгород', 'yandex' => '47', 'google' => ['1011981']],
        ['slug' => 'chelyabinsk', 'name' => 'Челябинск', 'yandex' => '56', 'google' => ['1011874']],
        ['slug' => 'samara', 'name' => 'Самара', 'yandex' => '51', 'google' => ['1012029']],
        ['slug' => 'omsk', 'name' => 'Омск', 'yandex' => '66', 'google' => ['1011985']],
        ['slug' => 'rostov', 'name' => 'Ростов-на-Дону', 'yandex' => '39', 'google' => ['1012013']],
    ],

    /**
     * Сходство выдач: |A∩B| / min(|A|,|B|).
     * ≥ independent_min → геонезависимый; иначе геозависимый.
     */
    'geo_independent_min_overlap' => 0.4,

    /** Коммерция: aggregators + ecommerce + organizations */
    'commerce_high' => 60,
    'commerce_low' => 35,

    /** Локализация: доля URL/доменов с региональными маркерами */
    'localization_high' => 50,
    'localization_low' => 15,

    /**
     * Основной + контрольный регион. Лимит = XML-страницы:
     * Яндекс: 1 стр. × 2 региона; Google: ceil(depth/10) × 2 региона.
     */
    'regions_per_check' => 2,

    /** Демо на titlo.ru/geo-lokalizaciya-kommerciya/ — POST /api/demo/geo-lokalizaciya-kommerciya/run */
    'demo' => [
        'max_runs_per_day' => 2,
        'max_phrase_chars' => 80,
        'depth' => 10,
        'yandex_lr' => '213',
        'google_lr' => '1011969',
    ],
];
