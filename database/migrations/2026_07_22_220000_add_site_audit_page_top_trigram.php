<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditPageTopTrigram extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('site_audit_pages', 'top_trigram')) {
                $table->string('top_trigram', 192)->nullable()->after('top_bigram_count');
            }
            if (! Schema::hasColumn('site_audit_pages', 'top_trigram_count')) {
                $table->unsignedInteger('top_trigram_count')->default(0)->after('top_trigram');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('site_audit_pages')) {
            return;
        }

        Schema::table('site_audit_pages', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('site_audit_pages', 'top_trigram_count')) {
                $cols[] = 'top_trigram_count';
            }
            if (Schema::hasColumn('site_audit_pages', 'top_trigram')) {
                $cols[] = 'top_trigram';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
}
