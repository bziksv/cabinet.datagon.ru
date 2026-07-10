<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEseninTextCheckSettingsTable extends Migration
{
    public function up(): void
    {
        Schema::create('esenin_text_check_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 128)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esenin_text_check_settings');
    }
}
