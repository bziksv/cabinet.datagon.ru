<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscoveredSourceToSiteAuditPages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'discovered_via')) {
                // sitemap|seed|home|link
                $table->string('discovered_via', 16)->nullable()->after('click_depth');
            }
            if (! Schema::hasColumn('site_audit_pages', 'discovered_from')) {
                // URL страницы-источника при via=link
                $table->text('discovered_from')->nullable()->after('discovered_via');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'discovered_from')) {
                $table->dropColumn('discovered_from');
            }
            if (Schema::hasColumn('site_audit_pages', 'discovered_via')) {
                $table->dropColumn('discovered_via');
            }
        });
    }
}
