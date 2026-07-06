<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTriggerCampaignsSystem extends Migration
{
    public function up(): void
    {
        Schema::create('trigger_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('trigger_type', 64);
            $table->unsignedInteger('trigger_days')->default(180);
            $table->unsignedInteger('coupon_bonus_value')->default(500);
            $table->unsignedInteger('coupon_expires_days')->default(30);
            $table->string('email_subject');
            $table->text('email_intro')->nullable();
            $table->text('email_body')->nullable();
            $table->string('channel', 32)->default('cvng');
            $table->timestamps();
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('redeem_mode', 32)->default('topup_bonus')->after('usage_mode');
            $table->unsignedBigInteger('trigger_campaign_id')->nullable()->after('created_by');
            $table->unsignedBigInteger('assigned_user_id')->nullable()->after('trigger_campaign_id');

            $table->foreign('trigger_campaign_id')
                ->references('id')
                ->on('trigger_campaigns')
                ->onDelete('set null');

            $table->foreign('assigned_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::create('trigger_campaign_dispatches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('trigger_campaign_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('promo_code_id');
            $table->string('status', 32)->default('pending');
            $table->boolean('is_test')->default(false);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->unsignedBigInteger('balance_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('trigger_campaign_id')->references('id')->on('trigger_campaigns')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->onDelete('cascade');
            $table->foreign('balance_id')->references('id')->on('balances')->onDelete('set null');
            $table->unique(['trigger_campaign_id', 'user_id', 'is_test'], 'trigger_campaign_user_test_unique');
        });

        $now = now();

        DB::table('trigger_campaigns')->insert([
            'slug' => 'inactive_180_days',
            'name' => 'Не заходил 180+ дней',
            'description' => 'Пользователь не заходил в кабинет более 180 дней — персональный одноразовый купон на баланс.',
            'is_active' => false,
            'trigger_type' => 'inactive_days',
            'trigger_days' => 180,
            'coupon_bonus_value' => 500,
            'coupon_expires_days' => 30,
            'email_subject' => 'Мы скучаем — подарок 500 ₽ в Титло',
            'email_intro' => 'Давно не виделись! Загляните в кабинет — мы подготовили для вас персональный промокод.',
            'email_body' => "За это время в Титло появились новые инструменты: мониторинг позиций, анализ конкурентов и удобное пополнение баланса с бонусами.\n\nВаш персональный промокод ниже — введите его в разделе «Баланс» и средства зачислятся сразу, без обязательного пополнения.",
            'channel' => 'cvng',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('trigger_campaign_dispatches');

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropForeign(['trigger_campaign_id']);
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn(['redeem_mode', 'trigger_campaign_id', 'assigned_user_id']);
        });

        Schema::dropIfExists('trigger_campaigns');
    }
}
