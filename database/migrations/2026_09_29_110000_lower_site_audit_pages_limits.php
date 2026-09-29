<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Страниц за проверку: Ultimate 10 000 → 5 000, Maximum 100 000 → 10 000.
 * Free/Optimal без изменений (100 / 1 000).
 */
class LowerSiteAuditPagesLimits extends Migration
{
    private const PAGE_LIMITS = [
        'Free' => 100,
        'Optimal' => 1000,
        'Ultimate' => 5000,
        'Maximum' => 10000,
    ];

    private const PREVIOUS = [
        'Free' => 100,
        'Optimal' => 1000,
        'Ultimate' => 10000,
        'Maximum' => 100000,
    ];

    public function up(): void
    {
        $this->applyLimits(self::PAGE_LIMITS);
    }

    public function down(): void
    {
        $this->applyLimits(self::PREVIOUS);
    }

    /**
     * @param  array<string,int>  $limits
     */
    private function applyLimits(array $limits): void
    {
        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'SiteAudit')->value('id');
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
