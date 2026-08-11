<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Авторасписания Site Audit = лимит проектов (можно поставить авто на каждый сайт).
 */
class AlignSiteAuditSchedulesWithProjects extends Migration
{
    private const LIMITS = [
        'Free' => 0,
        'Optimal' => 20,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    private const OLD = [
        'Free' => 0,
        'Optimal' => 2,
        'Ultimate' => 5,
        'Maximum' => 10,
    ];

    public function up(): void
    {
        $this->apply(self::LIMITS);
    }

    public function down(): void
    {
        $this->apply(self::OLD);
    }

    /**
     * @param  array<string,int>  $limits
     */
    private function apply(array $limits): void
    {
        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'SiteAuditSchedules')->value('id');
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
