<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedRegisteredNoTopupTriggerCampaign extends Migration
{
    public function up(): void
    {
        if (DB::table('trigger_campaigns')->where('slug', 'registered_no_topup_7_days')->exists()) {
            return;
        }

        $now = now();

        DB::table('trigger_campaigns')->insert([
            'slug' => 'registered_no_topup_7_days',
            'name' => 'Зарегистрировался, но не пополнил',
            'description' => 'Email подтверждён, баланс 0, прошло 7+ дней с регистрации, не было пополнений — standalone-купон «попробуйте без риска».',
            'is_active' => false,
            'is_paused' => false,
            'trigger_type' => 'registered_no_topup',
            'trigger_days' => 7,
            'coupon_bonus_value' => 150,
            'coupon_expires_days' => 14,
            'send_rate_per_minute' => 10,
            'email_subject' => 'Попробуйте Титло без риска — 150 ₽ на баланс',
            'email_intro' => 'Вы зарегистрировались в Титло, но ещё не пополняли баланс — мы подготовили для вас небольшой подарок.',
            'email_body' => "Персональный промокод ниже зачисляет средства сразу на баланс — без обязательного пополнения и без риска.\n\nАктивируйте его в разделе «Баланс» и попробуйте мониторинг сайтов, проверку ссылок или другие инструменты кабинета.",
            'email_subject_en' => 'Try Titlo risk-free — 150 ₽ balance credit',
            'email_intro_en' => 'You signed up for Titlo but have not topped up yet — we prepared a small gift for you.',
            'email_body_en' => "The personal promo code below credits your balance immediately — no payment required, no risk.\n\nActivate it on the Balance page and try site monitoring, link checks, or other cabinet tools.",
            'channel' => 'cvng',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('trigger_campaigns')
            ->where('slug', 'registered_no_topup_7_days')
            ->delete();
    }
}
