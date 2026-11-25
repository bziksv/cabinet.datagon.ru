<?php

namespace App\Notifications;

use App\PhraseCommerceRun;
use App\Services\TelegramBotService;
use App\Support\NotificationLocale;
use App\User;

class PhraseCommerceCompletedNotification
{
    /**
     * Telegram владельцу по завершении проверки фраз, если бот подключён.
     */
    public static function sendTelegram(User $user, PhraseCommerceRun $run, string $source = 'system'): bool
    {
        if (! $user->isTelegramConnected() || empty(config('app.telegram_bot_token'))) {
            return false;
        }

        NotificationLocale::apply($user);

        $results = is_array($run->results) ? $run->results : [];
        $summary = is_array($results['summary'] ?? null) ? $results['summary'] : [];

        $url = $run->history_id
            ? route('pages.phrase-commerce', ['history' => $run->history_id])
            : route('pages.phrase-commerce', ['run' => $run->id]);

        $title = trim((string) ($run->title ?: __('Phrase commerce')));
        $engines = $run->enginesLabel();
        $region = $run->regionsLabel();
        $done = (int) $run->phrases_done;
        $total = (int) $run->phrases_total;
        $cost = (int) $run->actual_cost;

        $geoDep = (int) ($summary['geo_dependent'] ?? 0);
        $geoInd = (int) ($summary['geo_independent'] ?? 0);
        $comm = (int) ($summary['commercial'] ?? 0);
        $info = (int) ($summary['informational'] ?? 0);

        $lines = [
            '✅ <b>Проверка фраз завершена</b>',
            '',
            '<b>Проект:</b> ' . e($title),
            '<b>ПС и регион:</b> ' . e($engines) . ' · ' . e($region),
            '<b>Проверено фраз:</b> ' . $done . ' из ' . $total,
            '<b>Геозависимые / независимые:</b> ' . $geoDep . ' / ' . $geoInd,
            '<b>Коммерческие / инфо:</b> ' . $comm . ' / ' . $info,
            '<b>Списано лимитов:</b> ' . number_format($cost, 0, '', ' '),
        ];

        if ($run->error_message) {
            $lines[] = '⚠️ ' . e($run->error_message);
        }

        $lines[] = '';
        $lines[] = '<a href="' . e($url) . '">Открыть результаты в кабинете</a>';

        $text = implode("\n", $lines);

        $markup = null;
        if (TelegramBotService::supportsInlineUrlButton($url)) {
            $markup = [
                'inline_keyboard' => [[
                    ['text' => 'Открыть результаты', 'url' => $url],
                ]],
            ];
        }

        return (new TelegramBotService((int) $user->chat_id))->sendMsg($text, $markup, [
            'event_id' => 'phrase-commerce-done',
            'user_id' => (int) $user->id,
            'source' => $source,
        ]);
    }
}
