<?php

use App\Services\Marketing\MarketingContentPlanImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Стартовый план «Маркетинг сайта» (titlo.ru) + импорт контент-плана (660 терминов, 56 тем блога).
 * Идемпотентно: этапы по slug, задачи — только при первом создании этапа; импорт не перетирает правки.
 */
class SeedMarketingSitePlan extends Migration
{
    private const TITLO_REPO = 'https://github.com/bziksv/titlo';

    public function up()
    {
        if (!Schema::hasTable('marketing_stages')) {
            return;
        }

        $now = now();

        foreach ($this->stages() as $sort => $stage) {
            if (DB::table('marketing_stages')->where('slug', $stage['slug'])->exists()) {
                continue;
            }

            $stageId = DB::table('marketing_stages')->insertGetId([
                'slug' => $stage['slug'],
                'title' => $stage['title'],
                'description' => $stage['description'] ?? null,
                'goal' => $stage['goal'] ?? null,
                'priority' => $stage['priority'],
                'status' => $stage['status'],
                'sort' => ($sort + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (($stage['tasks'] ?? []) as $i => $task) {
                DB::table('marketing_tasks')->insert([
                    'stage_id' => $stageId,
                    'title' => $task['title'],
                    'description' => $task['description'] ?? null,
                    'status' => $task['status'] ?? 'planned',
                    'priority' => $task['priority'] ?? $stage['priority'],
                    'links' => isset($task['links']) ? implode("\n", $task['links']) : null,
                    'done_at' => $task['done_at'] ?? null,
                    'commit_ref' => $task['commit_ref'] ?? null,
                    'notes' => $task['notes'] ?? null,
                    'sort' => ($i + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Не через config(): на проде migrate часто идёт при старом config:cache без cabinet-marketing.php.
        $file = database_path('data/marketing/seo-glossary-plan.md');
        if (is_file($file)) {
            app(MarketingContentPlanImporter::class)->importFile($file);
        }
    }

    public function down()
    {
        // Данные плана не удаляем: таблицы сносит миграция create_marketing_site_tables.
    }

    /** @return array<int, array<string, mixed>> */
    private function stages(): array
    {
        $p0 = self::TITLO_REPO . '/commit/f26a01f';
        $p1 = self::TITLO_REPO . '/commit/58367d1';

        return [
            [
                'slug' => 'seo-module-landings-p0',
                'title' => 'SEO лендингов модулей — P0',
                'goal' => 'Каждый из 26 лендингов модулей оптимизирован под свой целевой запрос',
                'description' => 'Базовая техническая и on-page оптимизация публичных страниц модулей titlo.ru.',
                'priority' => 'P0',
                'status' => 'done',
                'tasks' => [
                    ['title' => 'Canonical и og:url на всех страницах', 'description' => 'lib/seo.ts; тестовые копии -v1/-v2/-v3 ссылаются canonical на публичный модуль, noindex сохранён, Disallow LAB убран из robots.txt.', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => 'f26a01f', 'links' => [$p0]],
                    ['title' => 'Новые title и description для 26 модулей', 'description' => 'Title ≤ 60 символов вместе с « | Титло», description ≤ 160, под целевой запрос модуля.', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => 'f26a01f', 'links' => [$p0]],
                    ['title' => 'H1 модулей с ключевой фразой', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => 'f26a01f', 'links' => [$p0]],
                    ['title' => 'Sitemap: убран /demo/, стабильный lastmod', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => 'f26a01f', 'links' => [$p0]],
                    ['title' => 'Исправлена битая ссылка на проверку мета-тегов, убран «redbox»', 'description' => '/proverka-meta-tegov/ → /proverka-meta-tegov-online/, убрана ссылка на lk.redbox.su.', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => 'f26a01f', 'links' => [$p0]],
                    ['title' => 'Сверить частотность целевых запросов модулей', 'description' => 'Запросы для title подбирались без проверки частотности — сверить по проверке частотности и при необходимости скорректировать.', 'status' => 'planned', 'priority' => 'P2'],
                ],
            ],
            [
                'slug' => 'seo-module-landings-p1',
                'title' => 'P1: микроразметка, перелинковка, OG-превью',
                'goal' => 'Расширенные сниппеты и сильная внутренняя перелинковка модулей',
                'priority' => 'P1',
                'status' => 'done',
                'tasks' => [
                    ['title' => 'JSON-LD: FAQPage, BreadcrumbList, WebApplication на 26 модулях', 'description' => 'Плюс Organization и WebSite в layout. Цены и рейтинги в разметку не добавляли (тарифы на всю платформу).', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => '58367d1', 'links' => [$p1]],
                    ['title' => 'Ссылки на модули в шапке — в HTML, колонка «Инструменты» в подвале', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => '58367d1', 'links' => [$p1]],
                    ['title' => 'Блок «С чем стыкуется»: на каждый модуль ≥ 2 входящих ссылок', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => '58367d1', 'links' => [$p1]],
                    ['title' => 'OG-картинки 1200×630 для каждого модуля (Telegram, VK)', 'status' => 'done', 'done_at' => '2026-11-15', 'commit_ref' => '58367d1', 'links' => [$p1]],
                    ['title' => 'Проверить меню модулей в шапке на компьютере и телефоне', 'description' => 'Шапку переделали без скриншотов — нужна ручная проверка.', 'status' => 'planned', 'priority' => 'P1'],
                ],
            ],
            [
                'slug' => 'seo-glossary',
                'title' => 'SEO-словарь',
                'goal' => 'Раздел-словарь на titlo.ru: вход для новичков и низкочастотные запросы «что такое …», каждый термин ведёт в модуль',
                'description' => 'План: 660 терминов в 20 категориях (P1 — 153, P2 — 279, P3 — 228). Страницы у P1/P2, P3 — карточки в указателе. ТЗ каждой страницы — по анализу релевантности в Титло.',
                'priority' => 'P1',
                'status' => 'in_progress',
                'tasks' => [
                    ['title' => 'Собрать полный список терминов', 'description' => '660 терминов, 20 категорий, 351 связан с модулем; сверено с PR-CY, Serpstat, PromoPult, Топвизором, глоссарием Яндекс Вебмастера.', 'status' => 'done', 'done_at' => '2026-11-15', 'links' => ['titlo.ru/docs/seo-glossary-plan.md']],
                    ['title' => 'HTML-макет хаба словаря и страницы термина', 'description' => 'Хаб с поиском, алфавитом и категориями; страница «Алгоритм Панда».', 'status' => 'done', 'done_at' => '2026-11-15', 'links' => ['titlo.ru/docs/mockups/seo-slovar/']],
                    ['title' => 'Выбрать адрес раздела: /slovar/ или /glossary/', 'status' => 'planned', 'priority' => 'P1'],
                    ['title' => 'Пилотное ТЗ: «Семантическое ядро»', 'description' => 'Основной и доп. запросы, H2/H3, LSI из анализа релевантности, объём по ТОПу, модули, связанные термины. Оценить формат и тиражировать.', 'status' => 'in_progress', 'priority' => 'P1'],
                    ['title' => 'ТЗ для топ-20 терминов', 'status' => 'planned', 'priority' => 'P1'],
                    ['title' => 'Внедрить раздел на сайте', 'description' => 'Маршруты хаба и термина, JSON-LD DefinedTermSet / DefinedTerm, sitemap, ссылки в шапке и подвале.', 'status' => 'planned', 'priority' => 'P1'],
                    ['title' => 'Опубликовать первые 20 терминов', 'status' => 'planned', 'priority' => 'P1'],
                    ['title' => 'Уточнить, что значит «N&L»', 'description' => 'Кандидаты: NLP, LSI, noindex/nofollow, N-граммы, леммы.', 'status' => 'planned', 'priority' => 'P3'],
                ],
            ],
            [
                'slug' => 'blog-guides',
                'title' => 'Блог / гайды',
                'goal' => 'Статьи под то, что ищут сеошники и новички, с переходом в модули Титло',
                'description' => 'Контент-план: 56 тем, топ-10 отмечены ★. Термины словаря и статьи ссылаются друг на друга.',
                'priority' => 'P1',
                'status' => 'planned',
                'tasks' => [
                    ['title' => 'Решить адрес блога', 'description' => 'Сейчас /blog и /blog/ — постоянный редирект на /news/. Убрать редирект или взять другой адрес.', 'status' => 'planned'],
                    ['title' => 'ТЗ на топ-10 статей', 'status' => 'planned'],
                    ['title' => 'Первая статья: «Как собрать семантическое ядро сайта: пошаговый гайд»', 'status' => 'planned'],
                ],
            ],
            [
                'slug' => 'external-channels',
                'title' => 'Внешние каналы',
                'goal' => 'Трафик и ссылки с внешних площадок',
                'description' => 'Публикации и анонсы на внешних площадках (например, vc.ru) со ссылками на статьи и модули.',
                'priority' => 'P2',
                'status' => 'planned',
            ],
            [
                'slug' => 'yandex-direct',
                'title' => 'Яндекс Директ',
                'goal' => 'Платный трафик на ключевые модули',
                'priority' => 'P2',
                'status' => 'planned',
            ],
            [
                'slug' => 'free-tools',
                'title' => 'Бесплатные инструменты — низкий приоритет',
                'description' => 'Инструменты уже работают и дают трафик; отдельно в них сейчас не вкладываемся (решение от 15.11.2026).',
                'priority' => 'P3',
                'status' => 'postponed',
            ],
            [
                'slug' => 'comparison-pages',
                'title' => 'Страницы сравнения с конкурентами — отложено',
                'description' => 'Запросы «аналог / альтернатива / vs» — мало трафика (десятки–сотни показов), но горячая аудитория. Вернуться после словаря и блога.',
                'priority' => 'P3',
                'status' => 'postponed',
            ],
        ];
    }
}
