<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditPageContentUnchanged extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'content_unchanged')) {
                $table->boolean('content_unchanged')->default(false)->after('content_hash');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (Schema::hasColumn('site_audit_pages', 'content_unchanged')) {
                $table->dropColumn('content_unchanged');
            }
        });
    }
}
