<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKindToHomeUserArchivedSitesTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('home_user_archived_sites')) {
            return;
        }

        if (!Schema::hasColumn('home_user_archived_sites', 'kind')) {
            Schema::table('home_user_archived_sites', function (Blueprint $table) {
                $table->string('kind', 16)->default('archived')->after('domain');
                $table->index(['user_id', 'kind'], 'home_user_archived_sites_user_kind_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('home_user_archived_sites') || !Schema::hasColumn('home_user_archived_sites', 'kind')) {
            return;
        }

        Schema::table('home_user_archived_sites', function (Blueprint $table) {
            $table->dropIndex('home_user_archived_sites_user_kind_idx');
            $table->dropColumn('kind');
        });
    }
}
