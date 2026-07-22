<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label для публичной ссылки на отчёт аудита.
 */
class AddSiteAuditShareWhiteLabel extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_audit_crawls')) {
            return;
        }
        if (Schema::hasColumn('site_audit_crawls', 'share_white_label')) {
            return;
        }

        Schema::table('site_audit_crawls', function (Blueprint $table) {
            $table->boolean('share_white_label')->default(false)->after('share_enabled_at');
            $table->string('share_brand_name', 120)->nullable()->after('share_white_label');
            $table->string('share_brand_url', 255)->nullable()->after('share_brand_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_audit_crawls')) {
            return;
        }
        if (! Schema::hasColumn('site_audit_crawls', 'share_white_label')) {
            return;
        }

        Schema::table('site_audit_crawls', function (Blueprint $table) {
            $table->dropColumn(['share_white_label', 'share_brand_name', 'share_brand_url']);
        });
    }
}
