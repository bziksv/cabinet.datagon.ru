<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManagerAvatarToSeoReportProjects extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_report_projects')) {
            return;
        }

        Schema::table('seo_report_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('seo_report_projects', 'manager_avatar_path')) {
                $table->string('manager_avatar_path')->nullable()->after('manager_email');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('seo_report_projects')) {
            return;
        }

        Schema::table('seo_report_projects', function (Blueprint $table) {
            if (Schema::hasColumn('seo_report_projects', 'manager_avatar_path')) {
                $table->dropColumn('manager_avatar_path');
            }
        });
    }
}
