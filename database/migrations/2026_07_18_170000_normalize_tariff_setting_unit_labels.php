<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Единый формат подписей лимитов на /tariff:
 * (проверки) | (сохранения) | (проекты) | (страницы) | (ссылки)
 * Без «лимит в месяц», «проектов / сохранений результатов» и т.п.
 * Безлимитные модули — без скобок.
 */
class NormalizeTariffSettingUnitLabels extends Migration
{
    public function up()
    {
        $map = [
            'RelevanceAnalysis' => 'Анализатор Релевантности Страницы (проверки)',
            'TextAnalyzer' => 'Анализ текста страницы (проверки)',
            'CompetitorAnalysisPhrases' => 'Анализ конкурентов (проверки)',
            'SiteTypes' => 'Типы сайтов в выдаче (проверки / сохранения)',
            'SiteTypesHistory' => 'Типы сайтов в выдаче (сохранения)',
            'DomainRecords' => 'Записи домена (проверки / сохранения)',
            'DomainRecordsHistory' => 'Записи домена (сохранения)',
            'SearchSuggestions' => 'Сбор поисковых подсказок (проверки / сохранения)',
            'SearchSuggestionsHistory' => 'Сбор поисковых подсказок (сохранения)',
            'EseninTextCheck' => 'Проверка текста Есенин (проверки)',
            'IndexCheck' => 'Проверка индексации (проверки)',
            'Clusters' => 'Кластеризатор (проверки)',
            'domainMonitoringProject' => 'Мониторинг сайтов на доступность (проекты)',
            'monitoring' => 'Мониторинг позиций (проверки)',
            'DomainInformation' => 'Отслеживание срока регистрации доменов (проекты)',
            'MetaTagsProject' => 'Мониторинг Мета-Тегов (проекты)',
            'MetaTagsPages' => 'Мониторинг Мета-Тегов (страницы)',
            'BacklinkProject' => 'Отслеживание размещенных ссылок на сайтах (проекты)',
            'BacklinkLinks' => 'Отслеживание размещенных ссылок на сайтах (ссылки)',
            // безлимиты — без единиц
            'ListComparison' => 'Сравнение списков ключевых фраз',
            'HttpHeaders' => 'Проверка заголовков Http',
            'UniqueWords' => 'Выделение уникальных слов в тексте',
            'HtmlEditor' => 'Визуальный HTML-редактор',
            'RemoveDublicate' => 'Удаление дубликатов',
            'GeneratorWords' => 'Генератор слов',
            'PasswordGenerator' => 'Генератор паролей',
            'TextLength' => 'Подсчет длины текста',
            'ROI' => 'Калькулятор ROI',
            'UTM' => 'Генератор UTM-меток',
        ];

        foreach ($map as $code => $name) {
            DB::table('tariff_settings')->where('code', $code)->update(['name' => $name]);
        }
    }

    public function down()
    {
        // Не откатываем старую кашу имён — смысла нет.
    }
}
