<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedInactive30And90TriggerCampaigns extends Migration
{
    public function up(): void
    {
        $now = now();

        $campaigns = [
            [
                'slug' => 'inactive_30_days',
                'name' => 'Давно не заходили · 30',
                'description' => 'Мягкое напоминание, пока ещё помнят продукт — 30+ дней без входа в кабинет.',
                'is_active' => false,
                'is_paused' => false,
                'trigger_type' => 'inactive_days',
                'trigger_days' => 30,
                'coupon_bonus_value' => 200,
                'coupon_expires_days' => 14,
                'send_rate_per_minute' => 10,
                'email_subject' => 'Давно не заходили в Титло — небольшой подарок для вас',
                'email_intro' => 'Заметили, что вы давно не заглядывали в кабинет. Будем рады видеть вас снова!',
                'email_body' => "За это время в Титло появились полезные обновления: мониторинг сайтов, проверка ссылок и удобная работа с балансом.\n\nМы подготовили небольшой персональный промокод — активируйте его в разделе «Баланс», когда будет удобно вернуться.",
                'email_subject_en' => 'It has been a while — a small gift from Titlo',
                'email_intro_en' => 'We noticed you have not signed in for some time. We would love to see you back!',
                'email_body_en' => "Titlo has useful updates: site monitoring, link checks, and easier balance management.\n\nWe prepared a small personal promo code — activate it on the Balance page when you are ready to return.",
                'channel' => 'cvng',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'inactive_90_days',
                'name' => 'Давно не заходили · 90',
                'description' => 'Средний win-back — 90+ дней без входа, купон побольше.',
                'is_active' => false,
                'is_paused' => false,
                'trigger_type' => 'inactive_days',
                'trigger_days' => 90,
                'coupon_bonus_value' => 400,
                'coupon_expires_days' => 21,
                'send_rate_per_minute' => 10,
                'email_subject' => 'Скучаем по вам — 400 ₽ на баланс в Титло',
                'email_intro' => 'Вы давно не заходили в кабинет — мы сохранили для вас персональный промокод с увеличенным бонусом.',
                'email_body' => "Пока вас не было, в Титло добавились инструменты для SEO: мониторинг позиций, анализ конкурентов и автоматические уведомления о сбоях сайта.\n\nВаш промокод ниже — введите его в разделе «Баланс», и средства зачислятся сразу, без обязательного пополнения.",
                'email_subject_en' => 'We miss you — 400 ₽ balance credit in Titlo',
                'email_intro_en' => 'You have not signed in for a while — we saved a personal promo code with a larger bonus for you.',
                'email_body_en' => "While you were away, Titlo gained SEO tools: position monitoring, competitor analysis, and automatic site outage alerts.\n\nYour promo code is below — enter it on the Balance page and funds will be credited immediately, with no payment required.",
                'channel' => 'cvng',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($campaigns as $campaign) {
            if (DB::table('trigger_campaigns')->where('slug', $campaign['slug'])->exists()) {
                continue;
            }

            DB::table('trigger_campaigns')->insert($campaign);
        }

        DB::table('trigger_campaigns')
            ->where('slug', 'inactive_180_days')
            ->update([
                'name' => 'Давно не заходили · 180',
                'description' => 'Глубокий win-back — 180+ дней без входа, максимальный бонус.',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('trigger_campaigns')
            ->whereIn('slug', ['inactive_30_days', 'inactive_90_days'])
            ->delete();

        DB::table('trigger_campaigns')
            ->where('slug', 'inactive_180_days')
            ->update([
                'name' => 'Не заходил 180+ дней',
                'description' => 'Пользователь не заходил в кабинет более 180 дней — персональный одноразовый купон на баланс.',
                'updated_at' => now(),
            ]);
    }
}
