<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoindexSampleToSiteAuditPages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'noindex_sample')) {
                $table->string('noindex_sample', 191)->nullable()->after('noindex_text_len');
            }
            if (! Schema::hasColumn('site_audit_pages', 'noindex_links_json')) {
                $table->json('noindex_links_json')->nullable()->after('noindex_sample');
            }
            if (! Schema::hasColumn('site_audit_pages', 'noindex_hash')) {
                $table->string('noindex_hash', 32)->nullable()->after('noindex_links_json');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            foreach (['noindex_hash', 'noindex_links_json', 'noindex_sample'] as $col) {
                if (Schema::hasColumn('site_audit_pages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
