<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUptimeSinceToDomainMonitoringTable extends Migration
{
    public function up()
    {
        Schema::table('domain_monitoring', function (Blueprint $table) {
            $table->timestamp('uptime_since')->nullable()->after('up_time');
        });
    }

    public function down()
    {
        Schema::table('domain_monitoring', function (Blueprint $table) {
            $table->dropColumn('uptime_since');
        });
    }
}
