<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedUserFacingNewsSpringSummer2026 extends Migration
{
    private const AUTHOR_ID = 4;

    public function up(): void
    {
        foreach ($this->items() as $item) {
            DB::table('news')->insert([
                'user_id' => self::AUTHOR_ID,
                'content' => $item['content'],
                'files' => null,
                'number_of_likes' => 0,
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ]);
        }
    }

    public function down(): void
    {
        $dates = array_column($this->items(), 'created_at');

        DB::table('news')
            ->where('user_id', self::AUTHOR_ID)
            ->whereIn('created_at', $dates)
            ->delete();
    }

    /**
     * @return array<int, array{created_at: string, updated_at: string, content: string}>
     */
    private function items(): array
    {
        return [
            [
                'created_at' => '2026-05-20 11:30:00',
                'updated_at' => '2026-05-20 11:30:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p>На titlo.ru уточнили правила регистрации по e-mail.</p>
<p>Зарегистрироваться можно с адресом в зонах <strong>.ru</strong> или <strong>.su</strong>, либо на российском почтовом сервисе — Яндекс, Mail.ru и аналогичные. Адреса зарубежных сервисов (Gmail, Outlook и др.) при регистрации не принимаются.</p>
<p>Если указать неподходящий ящик, появится подсказка с правилами. Написать в службу поддержки можно с любого адреса на <a href="mailto:info@titlo.ru">info@titlo.ru</a>.</p>
<p>Те же правила действуют при смене e-mail в профиле.</p>
HTML,
            ],
            [
                'created_at' => '2026-06-10 14:00:00',
                'updated_at' => '2026-06-10 14:00:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p>В разделе <strong>«Мониторинг сайтов»</strong> уведомления теперь настраиваются отдельно для Telegram и для email.</p>
<p>У каждого проекта в таблице два переключателя: можно включить только Telegram, только письма или оба канала. На бесплатном тарифе письма о сбоях не отправляются — доступен Telegram, если бот подключён в профиле.</p>
<p>При попытке включить email на бесплатном тарифе кабинет покажет подсказку. Новые проекты на бесплатном тарифе создаются с выключенными уведомлениями — включите нужные каналы вручную.</p>
HTML,
            ],
            [
                'created_at' => '2026-06-28 10:15:00',
                'updated_at' => '2026-06-28 10:15:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p>В <strong>«Сроке регистрации доменов»</strong> напоминаем: уведомления о смене DNS и об окончании регистрации по-прежнему настраиваются отдельно для Telegram и для email в каждой строке таблицы.</p>
<p>На бесплатном тарифе email недоступен — работает Telegram при подключённом боте. Платные тарифы могут включать оба канала независимо друг от друга.</p>
HTML,
            ],
            [
                'created_at' => '2026-07-03 16:45:00',
                'updated_at' => '2026-07-03 16:45:00',
                'content' => <<<'HTML'
<p>Доброго дня!</p>
<p>В <strong>«Отслеживании ссылок»</strong> на странице «Мои проекты» появилась колонка уведомлений — как в мониторинге сайтов и сроке регистрации доменов.</p>
<p>Для каждого проекта можно отдельно включить Telegram и email о проблемных ссылках. На бесплатном тарифе письма недоступны; Telegram — если бот подключён в профиле. Новые проекты по умолчанию без уведомлений — включите нужный канал в списке проектов.</p>
HTML,
            ],
        ];
    }
}
