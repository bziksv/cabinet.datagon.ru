<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class sendNotificationAboutExpirationRegistrationPeriod extends Notification
{
    use LocalizesMailContent;
    use Queueable;

    public $project;

    public $diffInDays;

    /**
     * Create a new notification instance.
     *
     * @param $project
     * @param $diffInDays
     */
    public function __construct($project, $diffInDays)
    {
        $this->project = $project;
        $this->diffInDays = $diffInDays;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        $this->applyMailLocale($notifiable);

        return (new MailMessage)
            ->greeting(__('Mail notify greeting'))
            ->line(__('Mail notify auto disclaimer'))
            ->line(__('Mail notify dns domain', ['domain' => $this->project->domain]))
            ->line(__('Mail notify domain expire days', ['days' => $this->diffInDays]))
            ->action(__('Mail notify check your projects'), route('domain.information'))
            ->line(__('Mail notify thanks with us'));
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
