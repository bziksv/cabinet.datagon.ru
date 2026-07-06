<?php

namespace App\Support;

use Illuminate\Support\Facades\App;

class NotificationLocale
{
    /** @var string|null */
    private static $override;

    public static function override(?string $lang): void
    {
        self::$override = in_array($lang, ['ru', 'en'], true) ? $lang : null;
    }

    public static function clear(): void
    {
        self::$override = null;
    }

    public static function resolve($notifiable): string
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $lang = $notifiable->lang ?? 'ru';

        return in_array($lang, ['ru', 'en'], true) ? $lang : 'ru';
    }

    public static function apply($notifiable): string
    {
        $lang = self::resolve($notifiable);
        App::setLocale($lang);

        return $lang;
    }
}
