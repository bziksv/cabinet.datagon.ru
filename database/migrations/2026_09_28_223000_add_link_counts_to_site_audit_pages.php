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
            // Без AFTER: Instant ADD в конец (MariaDB) — иначе rebuild 48G и «table is full».
            if (! Schema::hasColumn('site_audit_pages', 'out_links_count')) {
                $table->unsignedInteger('out_links_count')->default(0);
            }
            if (! Schema::hasColumn('site_audit_pages', 'ext_links_count')) {
                $table->unsignedInteger('ext_links_count')->default(0);
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
