<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RelevancePublicSharesTtl extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('relevance_public_shares')) {
            return;
        }

        Schema::table('relevance_public_shares', function (Blueprint $table) {
            if (!Schema::hasColumn('relevance_public_shares', 'ttl_days')) {
                $table->unsignedSmallInteger('ttl_days')->nullable()->after('token');
            }
        });

        // Бессрочная ссылка: expires_at = null
        DB::statement('ALTER TABLE relevance_public_shares MODIFY expires_at TIMESTAMP NULL');

        DB::table('relevance_public_shares')
            ->whereNull('ttl_days')
            ->update(['ttl_days' => 30]);
    }

    public function down()
    {
        if (!Schema::hasTable('relevance_public_shares')) {
            return;
        }

        DB::table('relevance_public_shares')
            ->whereNull('expires_at')
            ->update(['expires_at' => DB::raw('DATE_ADD(NOW(), INTERVAL 30 DAY)')]);

        DB::statement('ALTER TABLE relevance_public_shares MODIFY expires_at TIMESTAMP NOT NULL');

        Schema::table('relevance_public_shares', function (Blueprint $table) {
            if (Schema::hasColumn('relevance_public_shares', 'ttl_days')) {
                $table->dropColumn('ttl_days');
            }
        });
    }
}
