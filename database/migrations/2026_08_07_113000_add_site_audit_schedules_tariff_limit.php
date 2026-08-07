<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Лимит авторасписаний Site Audit:
 * Free 0 / Optimal 2 / Ultimate 5 / Maximum 10
 */
class AddSiteAuditSchedulesTariffLimit extends Migration
{
    private const LIMITS = [
        'Free' => 0,
        'Optimal' => 2,
        'Ultimate' => 5,
        'Maximum' => 10,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        $sort = (int) (DB::table('tariff_setting_values')->max('sort') ?: 600) + 1;
        $code = 'SiteAuditSchedules';
        $name = 'Аудит сайта — авторасписаний';
        $description = 'Сколько проектов можно держать на автозапуске по расписанию. Free — без автоснятий.';
        $message = 'Лимит авторасписаний аудита сайта исчерпан ({VALUE}). Отключите другое расписание или увеличьте тариф.';

        $id = DB::table('tariff_settings')->where('code', $code)->value('id');
        if ($id) {
            DB::table('tariff_settings')->where('id', $id)->update([
                'name' => $name,
                'description' => $description,
                'message' => $message,
                'updated_at' => now(),
            ]);
        } else {
            $id = DB::table('tariff_settings')->insertGetId([
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::LIMITS as $tariff => $value) {
            $existing = DB::table('tariff_setting_values')
                ->where('tariff_setting_id', $id)
                ->where('tariff', $tariff)
                ->first();
            if ($existing) {
                DB::table('tariff_setting_values')->where('id', $existing->id)->update([
                    'value' => $value,
                    'sort' => $sort,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('tariff_setting_values')->insert([
                    'tariff_setting_id' => $id,
                    'tariff' => $tariff,
                    'value' => $value,
                    'sort' => $sort,
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
        $id = DB::table('tariff_settings')->where('code', 'SiteAuditSchedules')->value('id');
        if (! $id) {
            return;
        }
        if (Schema::hasTable('tariff_setting_user_values') && Schema::hasTable('tariff_setting_values')) {
            $valueIds = DB::table('tariff_setting_values')->where('tariff_setting_id', $id)->pluck('id');
            if ($valueIds->isNotEmpty()) {
                DB::table('tariff_setting_user_values')->whereIn('tariff_setting_value_id', $valueIds)->delete();
            }
        }
        DB::table('tariff_setting_values')->where('tariff_setting_id', $id)->delete();
        DB::table('tariff_settings')->where('id', $id)->delete();
    }
}
