<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddArchivedFromToSeoReports extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seo_reports')) {
            return;
        }
        if (!Schema::hasColumn('seo_reports', 'archived_from_report_id')) {
            Schema::table('seo_reports', function (Blueprint $table) {
                $table->unsignedBigInteger('archived_from_report_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('seo_reports') && Schema::hasColumn('seo_reports', 'archived_from_report_id')) {
            Schema::table('seo_reports', function (Blueprint $table) {
                $table->dropColumn('archived_from_report_id');
            });
        }
    }
}
