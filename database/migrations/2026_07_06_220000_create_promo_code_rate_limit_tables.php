<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromoCodeRateLimitTables extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_failed_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('code', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('promo_code_user_locks', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->timestamp('locked_until');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_user_locks');
        Schema::dropIfExists('promo_code_failed_attempts');
    }
}
