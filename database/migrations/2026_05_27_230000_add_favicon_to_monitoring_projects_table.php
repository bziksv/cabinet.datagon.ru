<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFaviconToMonitoringProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_projects', function (Blueprint $table) {
            $table->string('favicon_path', 255)->nullable()->after('url');
            $table->string('favicon_host', 255)->nullable()->after('favicon_path');
            $table->timestamp('favicon_updated_at')->nullable()->after('favicon_host');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_projects', function (Blueprint $table) {
            $table->dropColumn(['favicon_path', 'favicon_host', 'favicon_updated_at']);
        });
    }
}
