<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDomainRecordsHistory extends Migration
{
    private const HISTORY_LIMITS = [
        'Free' => 5,
        'Optimal' => 30,
        'Ultimate' => 50,
        'Maximum' => 100,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('domain_records_histories')) {
            Schema::create('domain_records_histories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('domain', 255);
                $table->json('snapshot');
                $table->timestamps();

                $table->index(['user_id', 'domain']);
                $table->index(['user_id', 'id']);
            });
        }

        if (Schema::hasTable('tariff_settings')
            && ! DB::table('tariff_settings')->where('code', 'DomainRecordsHistory')->exists()
        ) {
            $settingId = DB::table('tariff_settings')->insertGetId([
                'name' => 'Записи домена — сохранённых проверок',
                'code' => 'DomainRecordsHistory',
                'description' => 'Сколько снимков WHOIS/DNS хранить в истории для сравнения.',
                'message' => 'Достигнут лимит сохранённых проверок записей домена ({VALUE}). Удалите старые или повысьте тариф.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (self::HISTORY_LIMITS as $tariff => $value) {
                DB::table('tariff_setting_values')->insert([
                    'tariff_setting_id' => $settingId,
                    'tariff' => $tariff,
                    'value' => $value,
                    'sort' => 521,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tariff_settings')) {
            $settingId = DB::table('tariff_settings')->where('code', 'DomainRecordsHistory')->value('id');
            if ($settingId) {
                if (Schema::hasTable('tariff_setting_user_values') && Schema::hasTable('tariff_setting_values')) {
                    $valueIds = DB::table('tariff_setting_values')
                        ->where('tariff_setting_id', $settingId)
                        ->pluck('id');
                    if ($valueIds->isNotEmpty()) {
                        DB::table('tariff_setting_user_values')
                            ->whereIn('tariff_setting_value_id', $valueIds)
                            ->delete();
                    }
                }
                DB::table('tariff_setting_values')->where('tariff_setting_id', $settingId)->delete();
                DB::table('tariff_settings')->where('id', $settingId)->delete();
            }
        }

        Schema::dropIfExists('domain_records_histories');
    }
}
