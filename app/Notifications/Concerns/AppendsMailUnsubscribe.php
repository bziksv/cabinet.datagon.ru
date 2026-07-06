<?php

namespace App\Notifications\Concerns;

use App\Support\MailNotificationFooter;
use Illuminate\Notifications\Messages\MailMessage;

trait AppendsMailUnsubscribe
{
    protected function appendMailUnsubscribe(MailMessage $message, ?string $preferenceKey = null): MailMessage
    {
        return $message->line(MailNotificationFooter::unsubscribeMarkdown($preferenceKey));
    }
}
