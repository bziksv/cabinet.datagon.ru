<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Бесплатный тариф: авторасписания Site Audit = 0.
 */
class SiteAuditFreeSchedulesZero extends Migration
{
    public function up(): void
    {
        $this->setFree(0);
    }

    public function down(): void
    {
        $this->setFree(1);
    }

    private function setFree(int $value): void
    {
        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'SiteAuditSchedules')->value('id');
        if (! $settingId) {
            return;
        }

        $existing = DB::table('tariff_setting_values')
            ->where('tariff_setting_id', $settingId)
            ->where('tariff', 'Free')
            ->first();

        if ($existing) {
            DB::table('tariff_setting_values')->where('id', $existing->id)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tariff_setting_values')->insert([
            'tariff_setting_id' => $settingId,
            'tariff' => 'Free',
            'value' => $value,
            'sort' => (int) (DB::table('tariff_setting_values')->max('sort') ?: 600),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
