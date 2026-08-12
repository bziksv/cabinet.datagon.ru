<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTokenTopJsonToSiteAuditPages extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'token_top_json')) {
                $table->json('token_top_json')->nullable()->after('simhash');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }
        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'token_top_json')) {
                $table->dropColumn('token_top_json');
            }
        });
    }
}
