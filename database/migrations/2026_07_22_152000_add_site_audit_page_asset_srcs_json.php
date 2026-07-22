<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditPageAssetSrcsJson extends Migration
{
    public function up()
    {
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'asset_srcs_json')) {
                $table->json('asset_srcs_json')->nullable()->after('img_srcs_json');
            }
        });
    }

    public function down()
    {
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'asset_srcs_json')) {
                $table->dropColumn('asset_srcs_json');
            }
        });
    }
}
