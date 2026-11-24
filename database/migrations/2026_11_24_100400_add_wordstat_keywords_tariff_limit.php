<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddWordstatKeywordsTariffLimit extends Migration
{
    private const MONTHLY_LIMITS = [
        'Free' => 50,
        'Optimal' => 5000,
        'Ultimate' => 12000,
        'Maximum' => 30000,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $existing = DB::table('tariff_settings')->where('code', 'WordstatKeywords')->first();
        $settingId = $existing
            ? (int) $existing->id
            : (int) DB::table('tariff_settings')->insertGetId([
                'name' => 'Подбор слов Wordstat (запросов в месяц)',
                'code' => 'WordstatKeywords',
                'description' => 'Лимит запросов к Wordstat в календарный месяц (1 проверенная фраза = 1 запрос).',
                'message' => 'Лимит запросов Wordstat исчерпан ({VALUE} в месяц). Повысьте тариф или дождитесь нового месяца.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach (self::MONTHLY_LIMITS as $tariff => $value) {
            $row = DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $settingId)
                ->where('tariff', $tariff)
                ->first();

            if ($row) {
                DB::table('tariff_setting_values')
                    ->where('id', $row->id)
                    ->update(['value' => $value, 'updated_at' => now()]);
            } else {
                DB::table('tariff_setting_values')->insert([
                    'tariff_setting_id' => $settingId,
                    'tariff' => $tariff,
                    'value' => $value,
                    'sort' => 520,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $setting = DB::table('tariff_settings')->where('code', 'WordstatKeywords')->first();
        if (! $setting) {
            return;
        }

        if (Schema::hasTable('tariff_setting_user_values')) {
            DB::table('tariff_setting_user_values')->where('tariff_setting_id', $setting->id)->delete();
        }
        DB::table('tariff_setting_values')->where('tariff_setting_id', $setting->id)->delete();
        DB::table('tariff_settings')->where('id', $setting->id)->delete();
    }
}
