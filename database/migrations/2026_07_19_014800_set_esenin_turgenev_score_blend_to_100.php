<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Старое значение 50% разбавляло высокий риск внешнего отчёта (11 → 6/7).
 * Ставим 100%: показывать баллы как во внешнем отчёте.
 */
class SetEseninTurgenevScoreBlendTo100 extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('esenin_text_check_settings')) {
            return;
        }

        $now = now();
        $exists = DB::table('esenin_text_check_settings')
            ->where('code', 'provider.turgenev.score_blend_percent')
            ->exists();

        if ($exists) {
            DB::table('esenin_text_check_settings')
                ->where('code', 'provider.turgenev.score_blend_percent')
                ->update([
                    'value' => '100',
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('esenin_text_check_settings')->insert([
            'code' => 'provider.turgenev.score_blend_percent',
            'value' => '100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('esenin_text_check_settings')) {
            return;
        }

        DB::table('esenin_text_check_settings')
            ->where('code', 'provider.turgenev.score_blend_percent')
            ->update([
                'value' => '50',
                'updated_at' => now(),
            ]);
    }
}
