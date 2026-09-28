<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLinkCountsToSiteAuditPages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'out_links_count')) {
                $table->unsignedInteger('out_links_count')->default(0)->after('out_links_json');
            }
            if (! Schema::hasColumn('site_audit_pages', 'ext_links_count')) {
                $table->unsignedInteger('ext_links_count')->default(0)->after('ext_links_json');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'ext_links_count')) {
                $table->dropColumn('ext_links_count');
            }
            if (Schema::hasColumn('site_audit_pages', 'out_links_count')) {
                $table->dropColumn('out_links_count');
            }
        });
    }
}
