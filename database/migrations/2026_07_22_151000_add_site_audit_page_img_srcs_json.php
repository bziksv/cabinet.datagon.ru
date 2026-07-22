<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditPageImgSrcsJson extends Migration
{
    public function up()
    {
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'img_srcs_json')) {
                $table->json('img_srcs_json')->nullable()->after('out_links_json');
            }
        });
    }

    public function down()
    {
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'img_srcs_json')) {
                $table->dropColumn('img_srcs_json');
            }
        });
    }
}
