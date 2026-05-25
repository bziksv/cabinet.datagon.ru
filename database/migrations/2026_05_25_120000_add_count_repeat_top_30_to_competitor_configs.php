<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountRepeatTop30ToCompetitorConfigs extends Migration
{
    public function up()
    {
        Schema::table('competitor_configs', function (Blueprint $table) {
            $table->integer('count_repeat_top_30')->default(15)->after('count_repeat_top_20');
        });
    }

    public function down()
    {
        Schema::table('competitor_configs', function (Blueprint $table) {
            $table->dropColumn('count_repeat_top_30');
        });
    }
}
