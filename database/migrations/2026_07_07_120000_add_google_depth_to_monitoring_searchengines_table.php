<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleDepthToMonitoringSearchenginesTable extends Migration
{
    public function up()
    {
        Schema::table('monitoring_searchengines', function (Blueprint $table) {
            $table->unsignedTinyInteger('google_depth')->default(10)->after('lr');
        });
    }

    public function down()
    {
        Schema::table('monitoring_searchengines', function (Blueprint $table) {
            $table->dropColumn('google_depth');
        });
    }
}
