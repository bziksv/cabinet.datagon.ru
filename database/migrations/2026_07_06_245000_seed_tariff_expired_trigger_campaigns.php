<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedTariffExpiredTriggerCampaigns extends Migration
{
    public function up(): void
    {
        $now = now();

        $campaigns = [
            [
                'slug' => 'tariff_expired_7_days',
                'name' => 'Тариф истёк · 7 дней',
                'description' => 'Тариф закончился 7 дней назад, пользователь не продлил — персональный купон на возврат.',
                'trigger_type' => 'tariff_expired',
                'trigger_days' => 7,
                'coupon_bonus_value' => 10,
                'coupon_expires_days' => 21,
                'email_subject' => 'Тариф «:tariff_name» истёк — +10% к пополнению',
                'email_intro' => 'Ваш тариф :tariff_name закончился :expired_at. Мы сохранили для вас персональный промокод — +10% к сумме пополнения.',
                'email_body' => "Пока подписка не активна, часть платных инструментов недоступна — но ваши проекты и данные на месте.\n\nПополните баланс с промокодом ниже, затем оформите продление в «Тариф».",
                'email_subject_en' => 'Your :tariff_name plan expired — +10% on top-up',
                'email_intro_en' => 'Your :tariff_name plan expired on :expired_at. We saved a personal promo code — +10% on your top-up amount.',
                'email_body_en' => "While your subscription is inactive, some paid tools are unavailable — but your projects and data are still here.\n\nTop up your balance with the code below, then renew on the Tariff page.",
            ],
            [
                'slug' => 'tariff_expired_14_days',
                'name' => 'Тариф истёк · 14 дней',
                'description' => 'Тариф закончился 14 дней назад — усиленный купон на возврат.',
                'trigger_type' => 'tariff_expired',
                'trigger_days' => 14,
                'coupon_bonus_value' => 15,
                'coupon_expires_days' => 21,
                'email_subject' => 'Скучаем по вам — +15% к пополнению после окончания тарифа',
                'email_intro' => 'Прошло :days_since дней после окончания тарифа :tariff_name (:expired_at). Подготовили промокод — +15% к сумме пополнения.',
                'email_body' => "Вернитесь в Титло — мониторинг, проверка ссылок и другие инструменты ждут вас.\n\nПромокод ниже действует при пополнении баланса.",
                'email_subject_en' => 'We miss you — +15% on top-up after your plan expired',
                'email_intro_en' => 'It has been :days_since days since your :tariff_name plan expired (:expired_at). Here is a promo code — +15% on your top-up amount.',
                'email_body_en' => "Come back to Titlo — monitoring, link checks, and other tools are waiting.\n\nUse the code below when topping up your balance.",
            ],
        ];

        foreach ($campaigns as $campaign) {
            if (DB::table('trigger_campaigns')->where('slug', $campaign['slug'])->exists()) {
                continue;
            }

            DB::table('trigger_campaigns')->insert(array_merge($campaign, [
                'is_active' => false,
                'is_paused' => false,
                'send_rate_per_minute' => 10,
                'channel' => 'cvng',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('trigger_campaigns')
            ->whereIn('slug', ['tariff_expired_7_days', 'tariff_expired_14_days'])
            ->delete();
    }
}
