<?php

namespace App;

use App\Services\TelegramBotService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TelegramBot extends Model
{
    protected $guarded = [];
    protected $table = 'telegram_bot';

    public static function sendTestNotify()
    {
        $user = Auth::user();
        if ($user->chat_id) {
            (new TelegramBotService($user->chat_id))->sendMsg(__('Проверка получения уведомлений пройдена!'));
        }
    }

    public static function brokenDomainNotification($project, $chatId)
    {
        $link = TelegramBot::removeProtocol($project);
        $uptimePercent = round($project->uptime_percent, 2);
        $cabinetUrl = url(route('site.monitoring', [], false));

        $text = __('Project') . " <code>$project->project_name</code>  " . __('broken') . "
        " . __('Check time:') . " <code>$project->last_check</code>
        " . __('http code:') . " <code>$project->code</code>
        " . __('Condition:') . " <code>" . __($project->status) . "</code>
        " . __('Current uptime:') . " <code>$uptimePercent%</code>
        " . __('Go to the website') . "
        <a href='$link' target='_blank'>" . $link . "</a>
        " . __('Go to the service:') . "
        <a href='$cabinetUrl' target='_blank'>$cabinetUrl</a>";

        (new TelegramBotService($chatId))->sendMsg($text);
    }

    public static function repairedDomainNotification($project, $chatId)
    {
        $link = TelegramBot::removeProtocol($project);
        $uptimePercent = round($project->uptime_percent, 2);
        $cabinetUrl = url(route('site.monitoring', [], false));

        $text = __('Project') . " <code>$project->project_name</code>  " . __('repair') . "
        " . __('Check time:') . " <code>$project->last_check</code>
        " . __('Condition:') . " <code>" . __($project->status) . "</code>
        " . __('Current uptime:') . " <code>$uptimePercent%</code>
        " . __('Total time of the last breakdown:') . " <code>$project->total_time_last_breakdown</code> " . __('min') . "
        " . __('Go to the website') . "
        <a href='$link' target='_blank'>" . $link . "</a>
        " . __('Go to the service:') . "
        <a href='$cabinetUrl' target='_blank'>$cabinetUrl</a>";

        (new TelegramBotService($chatId))->sendMsg($text);
    }

    public static function sendNotificationAboutChangeStateProject($project, $chatId)
    {
        $text =
            __('Domain') .
            ' ' . $project->domain
            . "\n"
            . "\n"
            . $project->domain_information
            . "\n"
            . "\n"
            . __('Go to the service:')
            . " <a href='https://lk.redbox.su/domain-information' target='_blank'>https://lk.redbox.su/domain-information</a>";

        (new TelegramBotService($chatId))->sendMsg($text);
    }

    public static function sendSuccessMessage($chatId)
    {
        $text = __('You have successfully subscribed to the notification newsletter');

        (new TelegramBotService($chatId))->sendMsg($text);
    }

    public static function sendNotificationAboutChangeDNS($project, $chatId, $dns)
    {
        $text = __('Domain') . ' ' . $project->domain
            . "\n"
            . __('DNS CHANGED')
            . "\n"
            . __('old') . " " . $dns
            . "\n"
            . __('new') . " " . $project->dns
            . "\n"
            . "\n"
            . __('Go to the service:')
            . " <a href='https://lk.redbox.su/domain-information' target='_blank'>https://lk.redbox.su/domain-information</a>";

        (new TelegramBotService($chatId))->sendMsg($text);
    }

    public static function sendNotificationAboutExpirationRegistrationPeriod($project, $chatId, $diffInDays)
    {
        $text = __('Domain') . ' ' . $project->domain
            . "\n"
            . __('Notification of the expiration of the registration period')
            . "\n"
            . __('Registration ends after') . " $diffInDays " . __('days')
            . "\n"
            . "\n"
            . __('Go to the service:')
            . " <a href='https://lk.redbox.su/domain-information' target='_blank'>https://lk.redbox.su/domain-information</a>";

        (new TelegramBotService($chatId))->sendMsg($text);
    }

    /**
     * Сводка по проекту: одно сообщение вместо списка ссылок.
     */
    public static function brokenLinkProjectNotification(
        ProjectTracking $project,
        $chatId,
        int $problemCount,
        bool $isTest = false
    ): bool {
        $cabinetUrl = url(route('show.backlink', ['id' => $project->id], false));
        $cabinetUrlAttr = self::escapeTelegramHtml($cabinetUrl);
        $linkLabel = self::escapeTelegramHtml(__('Backlink telegram open project'));

        $title = self::escapeTelegramHtml(
            $isTest ? __('Backlink telegram project test title') : __('Backlink telegram project title')
        );

        $lines = [
            $title,
            self::escapeTelegramHtml(__('Backlink telegram project line', ['name' => $project->project_name ?? '—'])),
        ];

        if ($problemCount > 0) {
            $lines[] = self::escapeTelegramHtml(
                __('Backlink telegram problems count', ['count' => $problemCount])
            );
        } else {
            $lines[] = self::escapeTelegramHtml(__('Backlink telegram all links ok'));
        }

        if (TelegramBotService::supportsInlineUrlButton($cabinetUrl)) {
            $lines[] = self::escapeTelegramHtml(__('Backlink telegram go service'))
                . ' <a href="' . $cabinetUrlAttr . '">' . $linkLabel . '</a>';
            $replyMarkup = [
                'inline_keyboard' => [[
                    [
                        'text' => __('Backlink telegram open project'),
                        'url' => $cabinetUrl,
                    ],
                ]],
            ];
        } else {
            $lines[] = self::escapeTelegramHtml(__('Backlink telegram local url hint'))
                . "\n<code>" . $cabinetUrlAttr . '</code>';
            $replyMarkup = null;
        }

        return (new TelegramBotService($chatId))->sendMsg(implode("\n", $lines), $replyMarkup);
    }

    private static function escapeTelegramHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function removeProtocol($project)
    {
        $link = preg_replace('#^https?://#', '', rtrim($project->link, '/'));
        return preg_replace('/^www\./', '', $link);
    }
}
