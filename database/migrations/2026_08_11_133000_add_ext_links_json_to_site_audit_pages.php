<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExtLinksJsonToSiteAuditPages extends Migration
{
    public function up()
    {
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'ext_links_json')) {
                $table->json('ext_links_json')->nullable()->after('out_links_json');
            }
        });
    }

    public function down()
    {
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'ext_links_json')) {
                $table->dropColumn('ext_links_json');
            }
        });
    }
}
