<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateEseninTextCheckTariffLimits extends Migration
{
    private const LIMITS = [
        'Free' => 5,
        'Optimal' => 100,
        'Ultimate' => 300,
        'Maximum' => 700,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'EseninTextCheck')->value('id');
        if (! $settingId) {
            return;
        }

        foreach (self::LIMITS as $tariff => $value) {
            DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $settingId)
                ->where('tariff', $tariff)
                ->update([
                    'value' => $value,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $previous = [
            'Free' => 5,
            'Optimal' => 200,
            'Ultimate' => 500,
            'Maximum' => 1000,
        ];

        if (! Schema::hasTable('tariff_settings') || ! Schema::hasTable('tariff_setting_values')) {
            return;
        }

        $settingId = DB::table('tariff_settings')->where('code', 'EseninTextCheck')->value('id');
        if (! $settingId) {
            return;
        }

        foreach ($previous as $tariff => $value) {
            DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $settingId)
                ->where('tariff', $tariff)
                ->update([
                    'value' => $value,
                    'updated_at' => now(),
                ]);
        }
    }
}
