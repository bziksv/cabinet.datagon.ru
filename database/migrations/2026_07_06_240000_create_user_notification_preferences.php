<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserNotificationPreferences extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('preference_key', 96);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'preference_key'], 'user_notification_pref_unique');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('preference_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
}
