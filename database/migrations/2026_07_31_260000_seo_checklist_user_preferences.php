<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SeoChecklistUserPreferences extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seo_checklist_user_preferences')) {
            return;
        }

        Schema::create('seo_checklist_user_preferences', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('module_title', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('seo_checklist_user_preferences');
    }
}
