<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SiteAuditProjects: доменов → проверок (crawl) в памяти.
 * Снижаем лимиты: одна проверка жрёт диск (pages/findings), доменов больше не капим.
 * Free 1 / Optimal 10 / Ultimate 20 / Maximum 30.
 */
class SiteAuditProjectsAsStoredChecks extends Migration
{
    private const LIMITS = [
        'Free' => 1,
        'Optimal' => 10,
        'Ultimate' => 20,
        'Maximum' => 30,
    ];

    private const PREVIOUS = [
        'Free' => 1,
        'Optimal' => 20,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')->where('code', 'SiteAuditProjects')->update([
            'name' => 'Аудит сайта — проверок в памяти',
            'description' => 'Сколько проверок аудита хранится одновременно. Старые удаляются автоматически. Число доменов не ограничено.',
            'message' => 'Лимит хранимых проверок аудита сайта ({VALUE}). Старые проверки удаляются при новых запусках — или удалите вручную / увеличьте тариф.',
            'updated_at' => now(),
        ]);

        $this->applyLimits(self::LIMITS);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        DB::table('tariff_settings')->where('code', 'SiteAuditProjects')->update([
            'name' => 'Аудит сайта — проектов в памяти',
            'description' => 'Сколько доменов/проектов аудита можно хранить одновременно.',
            'message' => 'Лимит проектов аудита сайта исчерпан ({VALUE}). Удалите старый проект или увеличьте тариф.',
            'updated_at' => now(),
        ]);

        $this->applyLimits(self::PREVIOUS);
    }

    /**
     * @param  array<string,int>  $limits
     */
    private function applyLimits(array $limits): void
    {
        if (! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'SiteAuditProjects')->value('id');
        if (! $settingId) {
            return;
        }

        foreach ($limits as $tariff => $value) {
            $existing = DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $settingId)
                ->where('tariff', $tariff)
                ->first();
            if ($existing) {
                DB::table('tariff_setting_values')->where('id', $existing->id)->update([
                    'value' => $value,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('tariff_setting_values')->insert([
                    'tariff_setting_id' => $settingId,
                    'tariff' => $tariff,
                    'value' => $value,
                    'sort' => (int) (DB::table('tariff_setting_values')->max('sort') ?: 600),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
