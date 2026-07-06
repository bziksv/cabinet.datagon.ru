<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedRegisteredNoToolTriggerCampaigns extends Migration
{
    public function up(): void
    {
        $now = now();

        $campaigns = [
            [
                'slug' => 'registered_no_tool_7_days',
                'name' => 'Зарегистрировался, но не запустил инструмент',
                'description' => 'N дней с регистрации, в visit-statistics пусто или только «Главная» — 500 ₽ и короткий гайд по первым шагам.',
                'trigger_type' => 'registered_no_tool',
                'trigger_days' => 7,
                'coupon_bonus_value' => 500,
                'coupon_expires_days' => 21,
                'email_subject' => '500 ₽ и с чего начать в Титло',
                'email_intro' => 'Вы зарегистрировались в Титло, но ещё не пробовали инструменты кабинета — мы подготовили для вас бонус и короткий план первых шагов.',
                'email_body' => "Персональный промокод ниже зачисляет 500 ₽ на баланс без обязательного пополнения.\n\nНиже — три простых шага, с которых удобнее всего начать. После активации кода откройте кабинет и выберите первый инструмент.",
                'email_subject_en' => '500 ₽ and where to start in Titlo',
                'email_intro_en' => 'You signed up for Titlo but have not tried any cabinet tools yet — here is a bonus and a short plan for your first steps.',
                'email_body_en' => "The personal promo code below credits 500 ₽ to your balance with no payment required.\n\nFollow the three simple steps below. After activating the code, open the cabinet and pick your first tool.",
            ],
            [
                'slug' => 'registered_no_tool_14_days',
                'name' => 'Зарегистрировался, но не запустил инструмент · 14 дней',
                'description' => '14 дней с регистрации без запуска инструментов — усиленный бонус и гайд по активации.',
                'trigger_type' => 'registered_no_tool',
                'trigger_days' => 14,
                'coupon_bonus_value' => 500,
                'coupon_expires_days' => 21,
                'email_subject' => 'Всё ещё не начали? 500 ₽ и быстрый старт в Титло',
                'email_intro' => 'Прошло две недели с регистрации, а инструменты кабинета вы ещё не пробовали — давайте исправим это за пару минут.',
                'email_body' => "Мы сохранили для вас персональный промокод на 500 ₽ и собрали короткий маршрут первых шагов.\n\nАктивируйте код, затем откройте любой инструмент из гайда — мониторинг сайтов или проверку ссылок.",
                'email_subject_en' => 'Still haven\'t started? 500 ₽ and a quick start in Titlo',
                'email_intro_en' => 'Two weeks have passed since you signed up, but you have not tried any cabinet tools yet — let\'s fix that in a couple of minutes.',
                'email_body_en' => "We saved a personal 500 ₽ promo code for you and put together a short first-steps route.\n\nActivate the code, then open any tool from the guide — site monitoring or link checks.",
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
            ->whereIn('slug', ['registered_no_tool_7_days', 'registered_no_tool_14_days'])
            ->delete();
    }
}
