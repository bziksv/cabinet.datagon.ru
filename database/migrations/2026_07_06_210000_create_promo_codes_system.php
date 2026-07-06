<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePromoCodesSystem extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 64)->unique();
            $table->string('title')->nullable();
            $table->string('bonus_type', 16);
            $table->unsignedInteger('bonus_value');
            $table->string('usage_mode', 16);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('balance_id');
            $table->unsignedInteger('paid_sum');
            $table->unsignedInteger('bonus_sum');
            $table->timestamps();

            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('balance_id')->references('id')->on('balances')->onDelete('cascade');
            $table->unique('balance_id');
        });

        Schema::table('balances', function (Blueprint $table) {
            $table->unsignedInteger('paid_sum')->nullable()->after('sum');
            $table->unsignedInteger('bonus_sum')->default(0)->after('paid_sum');
            $table->unsignedBigInteger('promo_code_id')->nullable()->after('bonus_sum');
            $table->timestamp('credited_at')->nullable()->after('promo_code_id');

            $table->foreign('promo_code_id')
                ->references('id')
                ->on('promo_codes')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            $table->dropForeign(['promo_code_id']);
            $table->dropColumn(['paid_sum', 'bonus_sum', 'promo_code_id', 'credited_at']);
        });

        Schema::dropIfExists('promo_code_redemptions');
        Schema::dropIfExists('promo_codes');
    }
}
