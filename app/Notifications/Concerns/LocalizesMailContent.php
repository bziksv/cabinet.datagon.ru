<?php

namespace App\Notifications\Concerns;

use App\Support\NotificationLocale;

trait LocalizesMailContent
{
    protected function applyMailLocale($notifiable): string
    {
        return NotificationLocale::apply($notifiable);
    }
}
