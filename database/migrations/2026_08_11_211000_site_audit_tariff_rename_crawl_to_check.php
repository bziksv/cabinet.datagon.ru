<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * В UI тарифа «краул» → «проверка» (SiteAudit / SiteAuditCrawls / concurrency).
 */
class SiteAuditTariffRenameCrawlToCheck extends Migration
{
    public function up()
    {
        $map = [
            'SiteAudit' => [
                'name' => 'Аудит сайта — страниц за проверку',
                'message' => 'Лимит страниц за проверку аудита сайта исчерпан ({VALUE}). Увеличьте тариф.',
            ],
            'SiteAuditCrawls' => [
                'name' => 'Аудит сайта — проверок в месяц',
            ],
            'SiteAuditConcurrency' => [
                'description' => 'Максимум параллельных HTTP-запросов в одной проверке.',
            ],
        ];

        foreach ($map as $code => $fields) {
            DB::table('tariff_settings')->where('code', $code)->update($fields);
        }
    }

    public function down()
    {
        $map = [
            'SiteAudit' => [
                'name' => 'Аудит сайта — страниц за краул',
                'message' => 'Лимит страниц за краул аудита сайта исчерпан ({VALUE}). Увеличьте тариф.',
            ],
            'SiteAuditCrawls' => [
                'name' => 'Аудит сайта — краулов в месяц',
            ],
            'SiteAuditConcurrency' => [
                'description' => 'Максимум параллельных HTTP-запросов в одном крауле.',
            ],
        ];

        foreach ($map as $code => $fields) {
            DB::table('tariff_settings')->where('code', $code)->update($fields);
        }
    }
}
