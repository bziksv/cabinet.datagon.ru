<?php

/**
 * Определение типов сайтов в выдаче Яндекс / Google.
 * Каталоги доменов + эвристика по URL/HTML страниц из ТОПа.
 */
return [
    'version' => '1.2.3',

    'max_phrases' => 200,
    'depths' => [3, 5, 10, 20, 30],
    'default_depth' => 10,
    'default_yandex_lr' => '213',
    'default_google_lr' => '1011969',

    /**
     * Яндекс: 1 фраза = 1 лимит (глубина в одном XML-запросе).
     * Google: 1 фраза × ceil(depth/10) — ПС отдаёт ≤10 URL на страницу (ТОП-20=2, ТОП-30=3).
     */
    'google_page_size' => 10,

    'request_pause_ms' => 120,

    /**
     * Категории и базовые каталоги доменов (без www).
     * Пользователь может дополнить списки в форме — они мержатся поверх.
     */
    'categories' => [
        'aggregators' => [
            'label' => 'Агрегаторы',
            'short' => 'Агр.',
            'color' => '#7c3aed',
            'hint' => 'Объявления, маркетплейсы и сравнители (Ozon, WB, AliExpress, Avito, Маркет и т.п.).',
            'domains' => [
                'avito.ru', 'youla.ru', 'cian.ru', 'domofond.ru', 'n1.ru', 'realty.yandex.ru',
                'auto.ru', 'drom.ru', 'av.by', 'kufar.by', 'lalafo.kg',
                'ozon.ru', 'wildberries.ru', 'wb.ru', 'aliexpress.ru', 'aliexpress.com',
                'market.yandex.ru', 'megamarket.ru', 'goods.ru', 'sbermegamarket.ru',
                'price.ru', 'tiu.ru', 'satom.ru', 'regmarkets.ru', 'pulscen.ru',
                'booking.com', 'ostrovok.ru', 'tutu.ru', 'aviasales.ru', 'kayak.com',
                'tripadvisor.ru', 'trip.com', 'hotels.com',
                'hh.ru', 'superjob.ru', 'avito.work', 'zarplata.ru', 'rabota.ru',
                'profi.ru', 'youdo.com', 'fl.ru', 'freelance.ru', 'kwork.ru',
                'sravni.ru', 'banki.ru', 'bankiros.ru', 'vbr.ru',
                '2gis.ru', 'zoon.ru', 'yell.ru', 'flamp.ru',
                'blizko.ru', 'orgpage.ru', 'spravker.ru',
            ],
        ],
        'ecommerce' => [
            'label' => 'Интернет-магазины',
            'short' => 'ИМ',
            'color' => '#059669',
            'hint' => 'Собственные интернет-магазины брендов и ритейла (не маркетплейсы).',
            'domains' => [
                'lamoda.ru', 'mvideo.ru', 'eldorado.ru',
                'citilink.ru', 'dns-shop.ru', 'technopark.ru', 'svyaznoy.ru',
                'joom.com', 'shein.com',
                'detmir.ru', 'sportmaster.ru', 'decathlon.ru', 'adidas.ru', 'nike.com',
                'leroymerlin.ru', 'lemanapro.ru', 'petrovich.ru', 'vseinstrumenti.ru',
                'hoff.ru', 'divan.ru', 'askona.ru', 'ikea.com',
                'apteka.ru', 'eapteka.ru', 'zdravcity.ru', 'uteka.ru',
                'litres.ru', 'book24.ru', 'labirint.ru', 'chitai-gorod.ru',
                're-store.ru', 'apple.com', 'samsung.com', 'xiaomi.com',
                'goldapple.ru', 'rivegauche.ru', 'letu.ru', 'podrygka.ru',
                'vkusvill.ru', 'perekrestok.ru', 'lenta.com', 'okeydostavka.ru',
                'sbermarket.ru', 'samokat.ru', 'delivery-club.ru', 'eda.yandex.ru',
            ],
        ],
        'organizations' => [
            'label' => 'Организации и бизнес',
            'short' => 'Орг.',
            'color' => '#0284c7',
            'hint' => 'Сайты услуг и компаний: заказ в офлайне или через менеджера.',
            'domains' => [
                'sberbank.ru', 'vtb.ru', 'alfabank.ru', 'tinkoff.ru', 'gazprombank.ru',
                'mts.ru', 'megafon.ru', 'beeline.ru', 'tele2.ru', 'yota.ru',
                'rosneft.ru', 'lukoil.ru', 'gazprom.ru',
                'rzd.ru', 'aeroflot.ru', 's7.ru', 'pobeda.aero', 'uralairlines.ru',
                'rt.ru', 'domru.ru', 'ertelecom.ru', 'ttk.ru',
                'gosuslugi.ru', 'nalog.gov.ru', 'mos.ru', 'spb.ru',
                'invitro.ru', 'helix.ru', 'gemotest.ru', 'cmd-online.ru',
            ],
        ],
        'content' => [
            'label' => 'Контентные',
            'short' => 'Конт.',
            'color' => '#d97706',
            'hint' => 'Статьи, wiki, гайды, медиа без сильной коммерции.',
            'domains' => [
                'wikipedia.org', 'ru.wikipedia.org', 'wiktionary.org',
                'habr.com', 'vc.ru', 'dtf.ru', 'pikabu.ru',
                'medium.com', 'livejournal.com',
                'cyberleninka.ru', 'scholar.google.com',
                'drive2.ru', 'ivd.ru', 'forumhouse.ru',
                'kinopoisk.ru', 'imdb.com', 'ivi.ru', 'okko.tv', 'kino.mail.ru',
                'stackoverflow.com', 'stackexchange.com', 'github.com', 'gitlab.com',
                'docs.microsoft.com', 'developer.mozilla.org',
                't-j.ru', 'secretmag.ru',
            ],
        ],
        'social' => [
            'label' => 'Социальные сети',
            'short' => 'Соц.',
            'color' => '#db2777',
            'hint' => 'Соцсети и мессенджеры.',
            'domains' => [
                'vk.com', 'vk.ru', 'ok.ru', 'facebook.com', 'fb.com', 'instagram.com',
                'twitter.com', 'x.com', 'tiktok.com', 'linkedin.com',
                't.me', 'telegram.org', 'telegram.me',
                'max.ru', 'threads.net', 'pinterest.com', 'reddit.com',
                'dzen.ru', 'zen.yandex.ru', 'youtube.com', 'rutube.ru',
            ],
        ],
        'reviews' => [
            'label' => 'Отзовики',
            'short' => 'Отз.',
            'color' => '#ea580c',
            'hint' => 'Сайты с отзывами о товарах, услугах, работодателях.',
            'domains' => [
                'otzovik.com', 'irecommend.ru', 'otzyvru.com', 'yell.ru', 'zoon.ru',
                'flamp.ru', 'tripadvisor.ru', 'trustpilot.com', 'otzyvy.pro',
                'dreamjob.ru', 'pravda-sotrudnikov.ru', 'orabote.xyz',
            ],
        ],
        'news' => [
            'label' => 'Новости',
            'short' => 'Нов.',
            'color' => '#dc2626',
            'hint' => 'Новостные и общественно-политические издания.',
            'domains' => [
                'ria.ru', 'tass.ru', 'interfax.ru', 'rbc.ru', 'kommersant.ru',
                'vedomosti.ru', 'forbes.ru', 'lenta.ru', 'gazeta.ru', 'iz.ru',
                'mk.ru', 'kp.ru', 'aif.ru', 'fontanka.ru', 'bfm.ru',
                'bbc.com', 'cnn.com', 'reuters.com', 'meduza.io', 'thebell.io',
                'news.yandex.ru', 'news.google.com',
            ],
        ],
        'games' => [
            'label' => 'Онлайн-игры',
            'short' => 'Игры',
            'color' => '#4f46e5',
            'hint' => 'Сайты, где можно играть онлайн (не издатели без игры).',
            'domains' => [
                'vkplay.ru', 'playhop.com', 'crazygames.com', 'poki.com',
                'igroutka.ru', 'game-game.com.ru',
                'steamcommunity.com', 'store.steampowered.com',
                'roblox.com', 'minecraft.net', 'epicgames.com',
            ],
        ],
    ],

    /**
     * Порядок проверки при пересечении доменов (раньше = выше приоритет).
     * Агрегаторы и магазины важнее «контент» для коммерческого среза.
     */
    'match_priority' => [
        'aggregators',
        'ecommerce',
        'reviews',
        'social',
        'news',
        'games',
        'organizations',
        'content',
    ],

    /** Демо на titlo.ru/tipy-saitov-v-vydache/ — POST /api/demo/tipy-saitov-v-vydache/run */
    'demo' => [
        'max_runs_per_day' => 2,
        'max_phrase_chars' => 80,
        'depth' => 10,
        'max_rows' => 10,
        'yandex_lr' => '213',
        'google_lr' => '1011969',
    ],
];
