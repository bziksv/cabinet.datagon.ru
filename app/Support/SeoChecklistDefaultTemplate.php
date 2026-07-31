<?php

namespace App\Support;

/**
 * Системный шаблон SEO-чеклиста (этапы + задачи).
 * links: [{label, url|route, module}]
 */
class SeoChecklistDefaultTemplate
{
    public const CODE = 'seo_default_v1';

    /**
     * @return array<string, array{title:string,sort:int}>
     */
    public static function stages(): array
    {
        return [
            'onboarding' => ['title' => 'Онбординг и Work', 'sort' => 10],
            'connect' => ['title' => 'Подключения и доступы', 'sort' => 20],
            'tech_base' => ['title' => 'Базовая техподготовка', 'sort' => 30],
            'audits' => ['title' => 'Аудиты', 'sort' => 40],
            'meta_index' => ['title' => 'Мета-теги и индекс', 'sort' => 50],
            'structure' => ['title' => 'Структура и URL', 'sort' => 60],
            'commerce' => ['title' => 'Коммерция и UX', 'sort' => 70],
            'content' => ['title' => 'Контент и статьи', 'sort' => 80],
            'links' => ['title' => 'Ссылочное', 'sort' => 90],
            'control' => ['title' => 'Контроль и повторы', 'sort' => 100],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tasks(): array
    {
        $m = static function (string $label, string $path): array {
            return ['label' => $label, 'path' => $path];
        };

        return [
            // —— Онбординг ——
            self::t('onboarding', 10, 'docs_work_tables', 'Ознакомиться с документацией по ведению рабочих таблиц', 'any'),
            self::t('onboarding', 20, 'project_seo_header', 'Работа с проектом по SEO', 'any', 'Заголовок проекта: подставить домен.'),
            self::t('onboarding', 30, 'add_to_work', 'Добавить проект в work / SEO + DEV pass', 'pm', 'Добавляет проект-менеджер.'),
            self::t('onboarding', 40, 'logo_and_codes', 'Добавить наш логотип, добавить два кода на сайт', 'owner', 'Добавляет ответственный за проект.'),
            self::t('onboarding', 50, 'copy_work_sheet', 'Скопировать рабочий лист по работам, позициям и проработке релевантности и заполнять его', 'owner'),
            self::t('onboarding', 60, 'semantics_startpoint', 'Составить СЯ / сгруппировать стартпоинт', 'owner'),
            self::t('onboarding', 70, 'work_sheet_links', 'Написать ссылки на рабочий лист для добавления в WORK и иные таблицы', 'shared', 'Ответственный + проект-менеджер.'),
            self::t('onboarding', 80, 'target_pages', 'Проставить целевые страницы', 'owner'),

            // —— Подключения ——
            self::t('connect', 10, 'metrika_share', 'Добавить сайт в Я.Метрику / расшарить доступ специалисту', 'pm', null, false, false, null, [
                $m('Метрика в кабинете', '/home/variant-4'),
            ]),
            self::t('connect', 20, 'webmaster_add', 'Добавить сайт в Я.Вебмастер', 'owner'),
            self::t('connect', 30, 'webmaster_errors', 'Проверка ошибок сайта в Я.Вебмастер и постановка задач при выявлении', 'owner'),
            self::t('connect', 40, 'twogis_add', 'Добавить сайт в 2ГИС', 'owner'),
            self::t('connect', 50, 'ya_business', 'Добавить сайт в Яндекс Бизнес', 'owner'),
            self::t('connect', 60, 'metrika_dynamics', 'Проверка динамики показателей счётчика Яндекс.Метрика (посещаемость, страницы входа и т.д.)', 'owner', null, false, false, null, [
                $m('Метрика в кабинете', '/home/variant-4'),
            ]),
            self::t('connect', 70, 'ftp_client', 'Добавить в FTP-клиент', 'owner'),
            self::t('connect', 80, 'site_backup', 'Сделать бекап сайта до начала работ (если есть возможность)', 'owner'),
            self::t('connect', 90, 'positions_monitoring', 'Добавить СЯ на отслеживание позиций', 'owner', null, false, false, null, [
                $m('Мониторинг позиций', '/monitoring'),
            ]),
            self::t('connect', 100, 'uptime_monitoring', 'Поставить сайт на мониторинг доступности', 'owner', null, false, false, null, [
                $m('Мониторинг сайта', '/site-monitoring'),
            ]),

            // —— Техбаза ——
            self::t('tech_base', 10, 'ssl_cert', 'Установить SSL-сертификат', 'owner'),
            self::t('tech_base', 20, 'crossbrowser', 'Кроссбраузерность', 'owner'),
            self::t('tech_base', 30, 'crossplatform', 'Кроссплатформенность', 'owner'),
            self::t('tech_base', 40, 'policies', 'Проверить наличие политик', 'owner'),
            self::t('tech_base', 50, 'favicon', 'favicon.ico', 'owner'),
            self::t('tech_base', 60, 'main_mirror', 'Определить основное зеркало сайта (с www или без)', 'owner'),
            self::t('tech_base', 70, 'webmaster_region', 'Определить географическую принадлежность сайта в Я.Вебмастере (регион сайта)', 'owner'),
            self::t('tech_base', 80, 'index_trash', 'Проверка мусора в индексе', 'owner'),
            self::t('tech_base', 90, 'robots_txt', 'Внести правки в robots.txt', 'owner'),
            self::t('tech_base', 100, 'sitemap_xml', 'Сгенерировать / проверить корректность sitemap.xml', 'owner'),
            self::t('tech_base', 110, 'htaccess_edit', 'Отредактировать .htaccess', 'owner'),
            self::t('tech_base', 120, 'bitrix_redirects', 'Проверить сайт на редиректы (Битрикс-сайты)', 'owner'),
            self::t('tech_base', 130, 'utf8_encoding', 'Проверка кодировки UTF-8 на сайте', 'owner'),
            self::t('tech_base', 140, 'page_speed', 'Скорость загрузки сайта, размер кода', 'owner'),
            self::t('tech_base', 150, 'page_404', 'Проверить наличие 404-страницы и её оформление', 'owner'),
            self::t('tech_base', 160, 'index_php_html_dupes', 'Дубли страниц, доступность по index.php / index.html, правки .htaccess', 'owner'),
            self::t('tech_base', 170, 'breadcrumbs_presence', 'Проверить наличие хлебных крошек', 'owner'),
            self::t('tech_base', 180, 'broken_links', 'Проверить наличие битых ссылок', 'owner', null, false, false, null, [
                $m('Site audit', '/site-audit'),
            ]),

            // —— Аудиты ——
            self::t('audits', 10, 'top_prime_site_analyzer', 'Ознакомиться с отчётом Top.Prime + провести аудит (SiteAnalyzer → наш Site Audit)', 'owner', null, false, false, null, [
                $m('Site audit', '/site-audit'),
            ]),
            self::t('audits', 20, 'turgenev_check', 'Проверка текстов (ТУРГЕНЕВ / наши текстовые инструменты) на страницах сайта', 'owner'),
            self::t('audits', 30, 'uniqueness_main', 'Проверка уникальности на основных страницах сайта', 'owner'),
            self::t('audits', 40, 'netpeak_spider', '!(ВАЖНО) Проверка Netpeak Spider / Site Audit — выделить отдельные подзадачи на исправление', 'owner', null, true, true, null, [
                $m('Site audit', '/site-audit'),
            ]),
            self::t('audits', 50, 'hidden_links_viruses', 'Проверка сайта на скрытые ссылки, редиректы с телефона, вирусы и фреймы', 'owner'),
            self::t('audits', 60, 'backlinks_check', 'Проверка сайта на обратные ссылки', 'owner'),
            self::t('audits', 70, 'screaming_frog', 'Анализ сайта (Screaming Frog → наш Site Audit)', 'owner', null, false, false, null, [
                $m('Site audit', '/site-audit'),
            ]),
            self::t('audits', 80, 'empty_meta', 'Проверить пустые метатеги', 'owner', null, false, false, null, [
                $m('Site audit', '/site-audit'),
            ]),
            self::t('audits', 90, 'http_links', 'Проверить ссылки http', 'owner'),
            self::t('audits', 100, 'url_errors', 'Проверить ошибки в url', 'owner'),
            self::t('audits', 110, 'content_dupes', 'Проверить дублирование контента', 'owner'),
            self::t('audits', 120, 'thin_content', 'Проверить страницы с малым количеством контента', 'owner'),
            self::t('audits', 130, 'empty_200_bitrix', 'Проверить страницы без контента, но с ответом 200 (Битрикс)', 'owner'),
            self::t('audits', 140, 'image_sizes', 'Проверить размеры картинок', 'owner'),
            self::t('audits', 150, 'antiplagiat', 'Анализ сайта в Антиплагиате / наших модулях уникальности', 'owner'),
            self::t('audits', 160, 'labrika', 'Анализ сайта в Лабрике (при наличии — свой аналог)', 'owner'),

            // —— Мета / индекс ——
            self::t('meta_index', 10, 'title_generation', 'Проверить генерацию title — не должен быть один на все страницы', 'owner'),
            self::t('meta_index', 20, 'keywords_generation', 'Проверить генерацию keywords — не один на все / лучше скрыть', 'owner'),
            self::t('meta_index', 30, 'description_generation', 'Проверить генерацию description — не один на все', 'owner'),
            self::t('meta_index', 40, 'nofollow_outbound', 'Закрытие нерелевантных и немодерируемых исходящих ссылок', 'owner'),
            self::t('meta_index', 50, 'webmaster_excluded_1', 'Анализ Я.Вебмастера: Индексирование → Страницы в поиске → исключённые (МПК/НКС), группировка по кластерам', 'owner', null, true),
            self::t('meta_index', 60, 'webmaster_excluded_2', 'Анализ Я.Вебмастера (повторный): исключённые страницы (МПК/НКС)', 'owner', null, true),
            self::t('meta_index', 70, 'webmaster_in_search', 'Анализ Я.Вебмастера: Индексирование → Страницы в поиске', 'owner'),
            self::t('meta_index', 80, 'saved_copy_analysis', 'Анализ сохранённой копии: Главная / Категория / Товар или Услуга', 'owner'),
            self::t('meta_index', 90, 'noindex_tags', 'Анализ страниц на наличие и корректное использование <NOINDEX>', 'owner'),
            self::t('meta_index', 100, 'title_commerce_topo', 'Коммерческая добавка в title (Купить/Заказать), топоним + слова из семантики', 'owner'),
            self::t('meta_index', 110, 'title_toponyms', 'Топонимы в title', 'owner'),
            self::t('meta_index', 120, 'description_semantics', 'Ключевые слова в description — из жирных словосочетаний семантики', 'owner'),
            self::t('meta_index', 130, 'hidden_layers', '!(ВАЖНО) Анализ скрытых слоёв сайта (тест-фраза → облако в анализаторе)', 'owner', null, true),

            // —— Структура ——
            self::t('structure', 10, 'structure_filters', 'Анализ структуры: фильтры/категории для слов, не вошедших в родительскую', 'owner'),
            self::t('structure', 20, 'tag_tiles', 'Внедрение плитки тегов с названием блока и ссылками', 'owner'),
            self::t('structure', 30, 'hide_menu_categories', 'Скрытие категорий из меню / превью родителя (Битрикс — галочки, иначе — по платформе)', 'owner'),
            self::t('structure', 40, 'trailing_slash_glue', 'Проверка склейки последнего «/» в URL', 'owner'),
            self::t('structure', 50, 'trailing_slash_presence', 'Проверка наличия «/» в конце', 'owner'),
            self::t('structure', 60, 'chpu', 'ЧПУ', 'owner'),
            self::t('structure', 70, 'param_urls', 'URL с параметрами (пагинация, сортировка). Закрытие пагинаций и сортировок', 'owner'),
            self::t('structure', 80, 'meta_menu_names', 'Независимое управление мета-тегами и названиями для меню / категории / конечной страницы', 'owner'),
            self::t('structure', 90, 'adaptive', 'Адаптивность', 'owner'),

            // —— Коммерция / UX ——
            self::t('commerce', 10, 'phone_800', 'Городской номер или 8 800', 'owner'),
            self::t('commerce', 20, 'cities_list', 'Список городов (если работа по нескольким регионам)', 'owner'),
            self::t('commerce', 30, 'prices_currency', 'Цены и значение валюты', 'owner'),
            self::t('commerce', 40, 'delivery_payment', 'Данные о доставке/оплате', 'owner'),
            self::t('commerce', 50, 'copyright_year', 'Копирайт и год основания', 'owner'),
            self::t('commerce', 60, 'menu_structure', 'Меню должно содержать основные пункты структуры сайта', 'owner'),
            self::t('commerce', 70, 'button_hover', 'Кнопки — эффект наведения', 'owner'),
            self::t('commerce', 80, 'fonts_consistency', 'Шрифты — единообразие на странице', 'owner'),
            self::t('commerce', 90, 'work_hours', 'Время и дни работы в шапке или подвале', 'owner'),
            self::t('commerce', 100, 'site_search', 'Поиск по сайту', 'owner'),
            self::t('commerce', 110, 'work_region', 'Регион работы в шапке/подвале; для РФ — сквозной элемент + Доставка/О компании', 'owner'),
            self::t('commerce', 120, 'breadcrumbs_micro', 'Хлебные крошки на каждой странице (кроме главной) + микроразметка', 'owner'),
            self::t('commerce', 130, 'social_links', 'Ссылки на соцсети — проверить или запросить у клиента', 'owner'),
            self::t('commerce', 140, 'product_crosslinks', 'Товарная перелинковка в карточках (похожие/рекомендуемые)', 'owner'),
            self::t('commerce', 150, 'product_matrix', 'Товарная матрица — достаточный ассортимент относительно конкурентов', 'owner'),
            self::t('commerce', 160, 'buy_one_click', 'Покупка в 1 клик', 'owner'),
            self::t('commerce', 170, 'calculators', 'Калькуляторы стоимости/доставки или имитация', 'owner'),
            self::t('commerce', 180, 'product_blocks', 'В карточку: «С этим товаром покупают», «Недавно просмотренные»', 'owner'),
            self::t('commerce', 190, 'page_vacancies', 'Страница «Вакансии»', 'owner'),
            self::t('commerce', 200, 'page_about', 'Страница «О компании» — история, достижения, сертификаты', 'owner'),
            self::t('commerce', 210, 'page_contacts', 'Страница контактов: адрес, время, телефон, email, форма, реквизиты, карта', 'owner'),
            self::t('commerce', 220, 'feedback_forms', 'Проверить корректность работы форм обратной связи', 'owner'),

            // —— Контент ——
            self::t('content', 10, 'market_articles_order', 'Заказать и разместить маркетинговые статьи', 'owner'),
            self::t('content', 20, 'market_articles_title', 'Title к маркетинговым статьям', 'owner'),
            self::t('content', 30, 'market_articles_h', 'H1/H6 к маркетинговым статьям', 'owner'),
            self::t('content', 40, 'market_articles_relink', 'Перелинковать маркетинговые статьи', 'owner'),
            self::t('content', 50, 'market_articles_images', 'Подобрать и разместить изображения к маркетинговым статьям', 'owner'),
            self::t('content', 60, 'info_articles_order', 'Заказать и разместить информационные статьи', 'owner'),
            self::t('content', 70, 'info_articles_title', 'Title к информационным статьям', 'owner'),
            self::t('content', 80, 'info_articles_h', 'H1/H6 к информационным статьям', 'owner'),
            self::t('content', 90, 'info_articles_relink', 'Перелинковать информационные статьи', 'owner'),
            self::t('content', 100, 'info_articles_images', 'Подобрать и разместить изображения к информационным статьям', 'owner'),

            // —— Ссылки ——
            self::t('links', 10, 'miralinks', 'Создать кампании для проекта Miralinks', 'owner'),
            self::t('links', 20, 'sape', 'Создать кампании для проекта Sape', 'owner'),
            self::t('links', 30, 'kwork', 'Создать кампании для проекта kwork.ru', 'owner'),
            self::t('links', 40, 'spyglass', 'Анализ ссылочной массы (SEO SpyGlass / свой анализ ссылок)', 'owner'),
            self::t('links', 50, 'links_buy_recurring', 'Создать повторяющуюся задачу на закупку ссылок в необходимых сервисах', 'owner', null, false, false, 'monthly'),

            // —— Контроль ——
            self::t('control', 10, 'metrika_recheck', 'Повторная проверка динамики Метрики', 'owner', null, false, false, null, [
                $m('Метрика в кабинете', '/home/variant-4'),
            ]),
        ];
    }

    /**
     * @param array<int, array{label:string,url:string}>|null $links
     * @return array<string, mixed>
     */
    private static function t(
        string $stage,
        int $sort,
        string $code,
        string $title,
        string $role,
        ?string $help = null,
        bool $important = false,
        bool $subtasks = false,
        ?string $repeat = null,
        ?array $links = null
    ): array {
        $stages = self::stages();

        return [
            'code' => $code,
            'stage_key' => $stage,
            'stage_sort' => (int) ($stages[$stage]['sort'] ?? 0),
            'sort' => $sort,
            'title' => $title,
            'help' => $help,
            'role' => $role,
            'is_important' => $important || strpos($title, '!(ВАЖНО)') !== false || strpos($title, '!(Важно)') !== false,
            'allows_subtasks' => $subtasks,
            'repeat_rule' => $repeat,
            'links' => $links ?: [],
        ];
    }
}
