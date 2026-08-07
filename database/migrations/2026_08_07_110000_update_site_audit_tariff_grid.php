<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Новая сетка Site Audit:
 * Free 1 поток / 100 стр / 1 проект
 * Optimal 2 / 1000 / 20
 * Ultimate 4 / 10000 / 50
 * Maximum 8 / 100000 / 100
 */
class UpdateSiteAuditTariffGrid extends Migration
{
    private const PAGE_LIMITS = [
        'Free' => 100,
        'Optimal' => 1000,
        'Ultimate' => 10000,
        'Maximum' => 100000,
    ];

    private const CONCURRENCY_LIMITS = [
        'Free' => 1,
        'Optimal' => 2,
        'Ultimate' => 4,
        'Maximum' => 8,
    ];

    private const PROJECT_LIMITS = [
        'Free' => 1,
        'Optimal' => 20,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    public function up(): void
    {
        if (Schema::hasTable('tariff_settings')) {
            $sort = (int) (DB::table('tariff_setting_values')->max('sort') ?: 600);

            $this->upsertSetting(
                'SiteAudit',
                'Аудит сайта — страниц за краул',
                'Максимум URL в одном запуске аудита на один домен (можно указать меньше в форме).',
                'Лимит страниц за краул аудита сайта исчерпан ({VALUE}). Увеличьте тариф.',
                self::PAGE_LIMITS,
                $sort + 1
            );

            $this->upsertSetting(
                'SiteAuditConcurrency',
                'Аудит сайта — потоки',
                'Максимум параллельных HTTP-запросов в одном крауле.',
                'Лимит потоков аудита сайта ({VALUE}). Увеличьте тариф.',
                self::CONCURRENCY_LIMITS,
                $sort + 2
            );

            $this->upsertSetting(
                'SiteAuditProjects',
                'Аудит сайта — проектов в памяти',
                'Сколько доменов/проектов аудита можно хранить одновременно.',
                'Лимит проектов аудита сайта исчерпан ({VALUE}). Удалите старый проект или увеличьте тариф.',
                self::PROJECT_LIMITS,
                $sort + 3
            );
        }

        if (! Schema::hasTable('site_audit_user_state')) {
            Schema::create('site_audit_user_state', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->unique();
                $table->timestamp('became_free_at')->nullable();
                $table->timestamp('history_purged_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_audit_user_state')) {
            Schema::dropIfExists('site_audit_user_state');
        }

        if (! Schema::hasTable('tariff_settings')) {
            return;
        }

        foreach (['SiteAuditConcurrency', 'SiteAuditProjects'] as $code) {
            $id = DB::table('tariff_settings')->where('code', $code)->value('id');
            if (! $id) {
                continue;
            }
            if (Schema::hasTable('tariff_setting_user_values') && Schema::hasTable('tariff_setting_values')) {
                $valueIds = DB::table('tariff_setting_values')
                    ->where('tariff_setting_id', $id)
                    ->pluck('id');
                if ($valueIds->isNotEmpty()) {
                    DB::table('tariff_setting_user_values')
                        ->whereIn('tariff_setting_value_id', $valueIds)
                        ->delete();
                }
            }
            DB::table('tariff_setting_values')->where('tariff_setting_id', $id)->delete();
            DB::table('tariff_settings')->where('id', $id)->delete();
        }
    }

    private function upsertSetting(
        string $code,
        string $name,
        string $description,
        string $message,
        array $limits,
        int $sort
    ): void {
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

        foreach ($limits as $tariff => $value) {
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
}
