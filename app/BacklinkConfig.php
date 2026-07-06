<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BacklinkConfig extends Model
{
    protected $table = 'backlink_configs';

    protected $guarded = [];

    protected $casts = [
        'default_notify_telegram' => 'boolean',
        'default_notify_email' => 'boolean',
        'email_notifications_enabled' => 'boolean',
        'telegram_notifications_enabled' => 'boolean',
    ];

    public static function instance(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'default_notify_telegram' => (bool) config('cabinet-backlink.notifications.default_notify_telegram', false),
            'default_notify_email' => (bool) config('cabinet-backlink.notifications.default_notify_email', false),
            'email_notifications_enabled' => (bool) config('cabinet-backlink.notifications.email_enabled', true),
            'telegram_notifications_enabled' => (bool) config('cabinet-backlink.notifications.telegram_enabled', true),
        ]);
    }

    public static function emailEnabled(): bool
    {
        return (bool) static::instance()->email_notifications_enabled;
    }

    public static function telegramEnabled(): bool
    {
        return (bool) static::instance()->telegram_notifications_enabled;
    }
}
