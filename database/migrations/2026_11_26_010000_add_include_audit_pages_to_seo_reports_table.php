<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIncludeAuditPagesToSeoReportsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_reports') || Schema::hasColumn('seo_reports', 'include_audit_pages')) {
            return;
        }

        Schema::table('seo_reports', function (Blueprint $table) {
            $table->boolean('include_audit_pages')->default(false)->after('work_plan_text');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('seo_reports') || !Schema::hasColumn('seo_reports', 'include_audit_pages')) {
            return;
        }

        Schema::table('seo_reports', function (Blueprint $table) {
            $table->dropColumn('include_audit_pages');
        });
    }
}
