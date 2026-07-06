<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DomainInformationConfig extends Model
{
    protected $table = 'domain_information_configs';

    protected $guarded = [];

    protected $casts = [
        'default_check_dns' => 'boolean',
        'default_check_registration_date' => 'boolean',
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
            'expiration_alert_days' => (int) config('cabinet-domain-information.notifications.expiration_alert_days', 20),
            'default_check_dns' => (bool) config('cabinet-domain-information.notifications.default_check_dns', false),
            'default_check_registration_date' => (bool) config('cabinet-domain-information.notifications.default_check_registration_date', false),
            'email_notifications_enabled' => (bool) config('cabinet-domain-information.notifications.email_enabled', true),
            'telegram_notifications_enabled' => (bool) config('cabinet-domain-information.notifications.telegram_enabled', true),
        ]);
    }

    public static function expirationAlertDays(): int
    {
        $days = (int) static::instance()->expiration_alert_days;

        return max(1, min(365, $days));
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
