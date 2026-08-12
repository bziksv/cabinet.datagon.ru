<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHeadingsJsonToSiteAuditPages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'headings_json')) {
                $table->json('headings_json')->nullable()->after('h2_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'keywords_meta')) {
                $table->string('keywords_meta', 512)->nullable()->after('robots_meta');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'keywords_meta')) {
                $table->dropColumn('keywords_meta');
            }
            if (Schema::hasColumn('site_audit_pages', 'headings_json')) {
                $table->dropColumn('headings_json');
            }
        });
    }
}
