<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHomeUserSitesPreferencesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_user_sites_preferences')) {
            return;
        }

        Schema::create('home_user_sites_preferences', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->json('columns')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_user_sites_preferences');
    }
}
