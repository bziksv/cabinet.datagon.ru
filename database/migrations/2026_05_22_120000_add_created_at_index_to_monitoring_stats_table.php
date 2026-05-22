<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreatedAtIndexToMonitoringStatsTable extends Migration
{
    public function up()
    {
        Schema::table('monitoring_stats', function (Blueprint $table) {
            $table->index('created_at', 'monitoring_stats_created_at_index');
        });
    }

    public function down()
    {
        Schema::table('monitoring_stats', function (Blueprint $table) {
            $table->dropIndex('monitoring_stats_created_at_index');
        });
    }
}
