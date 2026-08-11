<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Максимум: больше запусков аудита в месяц (было 12 — упирались в шапке «Осталось»).
 * Проекты на Maximum уже 100 — подтверждаем.
 */
class BumpSiteAuditMaximumCrawlsTo100 extends Migration
{
    private const CRAWL_LIMITS = [
        'Free' => 2,
        'Optimal' => 20,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    private const PROJECT_LIMITS = [
        'Free' => 1,
        'Optimal' => 20,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $this->applyLimits('SiteAuditCrawls', self::CRAWL_LIMITS);
        $this->applyLimits('SiteAuditProjects', self::PROJECT_LIMITS);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $this->applyLimits('SiteAuditCrawls', [
            'Free' => 1,
            'Optimal' => 4,
            'Ultimate' => 8,
            'Maximum' => 12,
        ]);
    }

    /**
     * @param  array<string,int>  $limits
     */
    private function applyLimits(string $code, array $limits): void
    {
        $settingId = DB::table('tariff_settings')->where('code', $code)->value('id');
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
