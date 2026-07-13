<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonitoringPositionsTableLookupIndex extends Migration
{
    public function up()
    {
        Schema::table('monitoring_positions', function (Blueprint $table) {
            $table->index(
                ['monitoring_searchengine_id', 'monitoring_keyword_id', 'created_at'],
                'mon_pos_engine_kw_created_idx'
            );
        });
    }

    public function down()
    {
        Schema::table('monitoring_positions', function (Blueprint $table) {
            $table->dropIndex('mon_pos_engine_kw_created_idx');
        });
    }
}
