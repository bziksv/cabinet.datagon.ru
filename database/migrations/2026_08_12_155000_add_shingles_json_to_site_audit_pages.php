<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShinglesJsonToSiteAuditPages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'shingles_json')) {
                $after = Schema::hasColumn('site_audit_pages', 'token_top_json')
                    ? 'token_top_json'
                    : 'simhash';
                $table->json('shingles_json')->nullable()->after($after);
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'shingles_json')) {
                $table->dropColumn('shingles_json');
            }
        });
    }
}
