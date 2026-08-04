<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamIdToSeoReportProjectsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_report_projects')) {
            return;
        }
        if (Schema::hasColumn('seo_report_projects', 'team_id')) {
            return;
        }

        Schema::table('seo_report_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('seo_report_projects') || !Schema::hasColumn('seo_report_projects', 'team_id')) {
            return;
        }

        Schema::table('seo_report_projects', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
}
