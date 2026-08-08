<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTeamIdToSiteAuditProjectsTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_audit_projects')) {
            return;
        }
        if (Schema::hasColumn('site_audit_projects', 'team_id')) {
            return;
        }

        Schema::table('site_audit_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_audit_projects') || ! Schema::hasColumn('site_audit_projects', 'team_id')) {
            return;
        }

        Schema::table('site_audit_projects', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
}
