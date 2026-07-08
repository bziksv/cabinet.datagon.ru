<?php

use App\Classes\Tariffs\FreeTariff;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ExtendActivePaidTariffsTwoDaysCompensation extends Migration
{
    private const BONUS_DAYS = 2;

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('tariff_pays')
            ->where('status', true)
            ->where('active_to', '>', $now)
            ->where('class_tariff', '!=', FreeTariff::class)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $activeTo = Carbon::parse($row->active_to);

                    DB::table('tariff_pays')
                        ->where('id', $row->id)
                        ->update([
                            'active_to' => $activeTo->copy()->addDays(self::BONUS_DAYS),
                            'updated_at' => $now,
                        ]);
                }
            });
    }

    public function down(): void
    {
        $now = Carbon::now();

        DB::table('tariff_pays')
            ->where('status', true)
            ->where('active_to', '>', $now)
            ->where('class_tariff', '!=', FreeTariff::class)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $activeTo = Carbon::parse($row->active_to);

                    DB::table('tariff_pays')
                        ->where('id', $row->id)
                        ->update([
                            'active_to' => $activeTo->copy()->subDays(self::BONUS_DAYS),
                            'updated_at' => $now,
                        ]);
                }
            });
    }
}
