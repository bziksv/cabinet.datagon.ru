<?php

/**
 * Регистрация: только .ru/.su или российские почтовые сервисы (titlo.ru и поддомены).
 */
return [
    'enabled' => env('SIGNUP_EMAIL_POLICY_ENABLED', true),

    /** Точные хосты кабинета / маркетинга. */
    'hosts' => [
        'titlo.ru',
        'www.titlo.ru',
        'cabinet.titlo.ru',
    ],

    /** Любой *.titlo.ru (cabinet.titlo.ru, demo.titlo.ru, …). */
    'host_suffixes' => [
        '.titlo.ru',
    ],

    /** На localhost — по умолчанию как на prod при APP_ENV=local (для сверки с almamed). */
    'enforce_on_local' => env(
        'SIGNUP_EMAIL_POLICY_ENFORCE_LOCAL',
        env('APP_ENV', 'production') === 'local'
    ),

    /** Локальные хосты разработки (localhost:3002 и т.п.). */
    'local_hosts' => [
        'localhost',
        '127.0.0.1',
    ],

    'allowed_tlds' => ['ru', 'su'],

    'allowed_providers' => [
        'mail.ru',
        'inbox.ru',
        'list.ru',
        'bk.ru',
        'internet.ru',
        'yandex.ru',
        'ya.ru',
        'yandex.com',
        'yandex.by',
        'yandex.kz',
        'yandex.ua',
        'rambler.ru',
        'lenta.ru',
        'autorambler.ru',
        'ro.ru',
        'pochta.ru',
        'e-mail.ru',
        'qip.ru',
        'live.ru',
    ],

    'support_email' => env('SIGNUP_EMAIL_POLICY_SUPPORT', 'info@titlo.ru'),
    'support_phone' => env('SIGNUP_EMAIL_POLICY_PHONE', null),
];
