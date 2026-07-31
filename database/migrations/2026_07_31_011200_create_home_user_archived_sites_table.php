<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHomeUserArchivedSitesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_user_archived_sites')) {
            return;
        }

        Schema::create('home_user_archived_sites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('domain', 255);
            $table->timestamps();

            $table->unique(['user_id', 'domain'], 'home_user_archived_sites_user_domain_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_user_archived_sites');
    }
}
