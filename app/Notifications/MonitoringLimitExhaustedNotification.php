<?php

namespace App\Notifications;

use App\Contracts\EmailPreferenceAware;
use App\Notifications\Concerns\AppendsMailUnsubscribe;
use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class MonitoringLimitExhaustedNotification extends Notification implements EmailPreferenceAware
{
    use AppendsMailUnsubscribe;
    use LocalizesMailContent;
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $this->applyMailLocale($notifiable);

        return $this->appendMailUnsubscribe(
            (new MailMessage)
            ->greeting(__('Mail notify greeting'))
            ->subject(__('Mail notify monitoring limit subject'))
            ->line(__('Mail notify auto disclaimer'))
            ->line(__('Mail notify monitoring limit line'))
            ->line(__('Mail notify thanks service')),
            $this->emailPreferenceKey()
        );
    }

    public function emailPreferenceKey(): ?string
    {
        return 'monitoring-limit-exhausted';
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
