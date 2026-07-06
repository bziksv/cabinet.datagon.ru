<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BrokenDomainNotification extends Notification
{
    use LocalizesMailContent;
    use Queueable;

    public $project;

    /** @var string */
    public $dispatchEventId = 'site-mon-broken';

    /**
     * Create a new notification instance.
     *
     * @param $project
     */
    public function __construct($project, string $dispatchEventId = 'site-mon-broken')
    {
        $this->project = $project;
        $this->dispatchEventId = $dispatchEventId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable): array
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
            ->subject(__('Mail notify site broken subject'))
            ->line(__('Mail notify auto disclaimer'))
            ->line(__('Mail notify site broken line', ['url' => $this->project->link]))
            ->line(__('Mail notify site broken status', ['code' => $this->project->code]))
            ->line(__('Mail notify site broken state unexpected'))
            ->line(__('Mail notify site broken uptime', ['percent' => $this->project->uptime_percent]))
            ->action(__('Mail notify check projects'), route('site.monitoring'))
            ->line(__('Mail notify thanks service'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
