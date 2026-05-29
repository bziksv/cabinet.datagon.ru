<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTop1ToMonitoringDataTableColumnsProjects extends Migration
{
    public function up()
    {
        Schema::table('monitoring_data_table_columns_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('monitoring_data_table_columns_projects', 'top1')) {
                $table->decimal('top1', 8, 2)->nullable()->after('middle');
                $table->string('diff_top1')->nullable()->after('top1');
            }
        });
    }

    public function down()
    {
        Schema::table('monitoring_data_table_columns_projects', function (Blueprint $table) {
            if (Schema::hasColumn('monitoring_data_table_columns_projects', 'top1')) {
                $table->dropColumn(['top1', 'diff_top1']);
            }
        });
    }
}
