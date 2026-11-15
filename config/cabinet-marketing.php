<?php

/**
 * «Маркетинг сайта» — план продвижения titlo.ru (только Super Admin / admin).
 * Таблицы: marketing_stages, marketing_tasks, marketing_content_items.
 */
return [
    'version' => '1.0.0',

    /** Статусы этапов и задач. */
    'task_statuses' => [
        'planned' => ['title' => 'Запланировано', 'badge' => 'text-bg-secondary'],
        'in_progress' => ['title' => 'В работе', 'badge' => 'text-bg-primary'],
        'done' => ['title' => 'Готово', 'badge' => 'text-bg-success'],
        'postponed' => ['title' => 'Отложено', 'badge' => 'text-bg-warning'],
    ],

    /** Статусы единиц контент-плана (термины словаря, статьи блога). */
    'content_statuses' => [
        'idea' => ['title' => 'Идея', 'badge' => 'text-bg-secondary'],
        'brief_ready' => ['title' => 'ТЗ готово', 'badge' => 'text-bg-info'],
        'writing' => ['title' => 'Пишется', 'badge' => 'text-bg-primary'],
        'review' => ['title' => 'На проверке', 'badge' => 'text-bg-warning'],
        'published' => ['title' => 'Опубликовано', 'badge' => 'text-bg-success'],
    ],

    'content_types' => [
        'term' => ['title' => 'Термин словаря', 'short' => 'Термин'],
        'article' => ['title' => 'Статья блога', 'short' => 'Статья'],
    ],

    'priorities' => ['P0', 'P1', 'P2', 'P3'],

    /** Публичный сайт — для ссылок на модули и опубликованные страницы. */
    'site_url' => 'https://titlo.ru',

    /** Файл плана, который импортирует MarketingContentPlanImporter (копия titlo.ru/docs/seo-glossary-plan.md). */
    'content_plan_file' => database_path('data/marketing/seo-glossary-plan.md'),

    'per_page' => 50,
];
