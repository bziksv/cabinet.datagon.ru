<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedTariffExpiringTriggerCampaigns extends Migration
{
    public function up(): void
    {
        $now = now();

        $campaigns = [
            [
                'slug' => 'tariff_expiring_7_days',
                'name' => 'Тариф скоро закончится · 7 дней',
                'description' => 'Напоминание за 7 дней до active_to — без бонусов, только продление.',
                'trigger_type' => 'tariff_expiring',
                'trigger_days' => 7,
                'coupon_bonus_value' => 0,
                'coupon_expires_days' => 1,
                'email_subject' => 'Тариф «:tariff_name» заканчивается через 7 дней',
                'email_intro' => 'Напоминаем: ваш тариф :tariff_name активен до :active_to.',
                'email_body' => "Чтобы не потерять доступ к платным инструментам, продлите подписку заранее.\n\nВсе данные и проекты сохранятся — достаточно оформить продление в разделе «Тариф».",
                'email_subject_en' => 'Your :tariff_name plan ends in 7 days',
                'email_intro_en' => 'Reminder: your :tariff_name plan is active until :active_to.',
                'email_body_en' => "To keep access to paid tools, renew your subscription in advance.\n\nYour data and projects will stay in place — renew on the Tariff page.",
            ],
            [
                'slug' => 'tariff_expiring_3_days',
                'name' => 'Тариф скоро закончится · 3 дня',
                'description' => 'Напоминание за 3 дня до active_to.',
                'trigger_type' => 'tariff_expiring',
                'trigger_days' => 3,
                'coupon_bonus_value' => 0,
                'coupon_expires_days' => 1,
                'email_subject' => 'Осталось 3 дня — тариф «:tariff_name»',
                'email_intro' => 'Через 3 дня (:active_to) заканчивается ваш тариф :tariff_name.',
                'email_body' => "Продлите подписку сейчас, чтобы работа в кабинете не прерывалась.\n\nПерейдите в раздел «Тариф» — там же видны срок и текущий план.",
                'email_subject_en' => '3 days left — :tariff_name plan',
                'email_intro_en' => 'In 3 days (:active_to) your :tariff_name plan will expire.',
                'email_body_en' => "Renew now to avoid interruption.\n\nOpen the Tariff page to see your plan and renewal options.",
            ],
            [
                'slug' => 'tariff_expiring_1_day',
                'name' => 'Тариф скоро закончится · 1 день',
                'description' => 'Последнее напоминание за 1 день до active_to.',
                'trigger_type' => 'tariff_expiring',
                'trigger_days' => 1,
                'coupon_bonus_value' => 0,
                'coupon_expires_days' => 1,
                'email_subject' => 'Завтра заканчивается тариф «:tariff_name»',
                'email_intro' => 'Завтра (:active_to) истекает срок вашего тарифа :tariff_name.',
                'email_body' => "Это последнее напоминание перед окончанием подписки.\n\nПродлите тариф сегодня — так вы сохраните доступ ко всем функциям без паузы.",
                'email_subject_en' => 'Your :tariff_name plan expires tomorrow',
                'email_intro_en' => 'Tomorrow (:active_to) your :tariff_name plan expires.',
                'email_body_en' => "This is the final reminder before your subscription ends.\n\nRenew today to keep uninterrupted access to all features.",
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
            ->whereIn('slug', [
                'tariff_expiring_7_days',
                'tariff_expiring_3_days',
                'tariff_expiring_1_day',
            ])
            ->delete();
    }
}
