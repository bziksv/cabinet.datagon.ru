<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeoChecklistTimeByDay extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('seo_checklist_item_time_logs')) {
            return;
        }

        if (!Schema::hasColumn('seo_checklist_item_time_logs', 'work_date')) {
            Schema::table('seo_checklist_item_time_logs', function (Blueprint $table) {
                $table->date('work_date')->nullable()->after('user_id')->index();
            });
        }

        // Для закрытых сессий — день старта (ночные сессии дальше режем при stop)
        DB::table('seo_checklist_item_time_logs')
            ->whereNull('work_date')
            ->whereNotNull('started_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $date = substr((string) $row->started_at, 0, 10);
                    if ($date === '' || $date === '0000-00-00') {
                        continue;
                    }
                    DB::table('seo_checklist_item_time_logs')
                        ->where('id', $row->id)
                        ->update(['work_date' => $date]);
                }
            });
    }

    public function down()
    {
        if (Schema::hasTable('seo_checklist_item_time_logs')
            && Schema::hasColumn('seo_checklist_item_time_logs', 'work_date')) {
            Schema::table('seo_checklist_item_time_logs', function (Blueprint $table) {
                $table->dropColumn('work_date');
            });
        }
    }
}
