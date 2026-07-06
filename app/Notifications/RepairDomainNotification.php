<?php

namespace App\Notifications;

use App\Contracts\EmailPreferenceAware;
use App\Notifications\Concerns\AppendsMailUnsubscribe;
use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RepairDomainNotification extends Notification implements EmailPreferenceAware
{
    use AppendsMailUnsubscribe;
    use LocalizesMailContent;
    use Queueable;

    public $project;

    /**
     * Create a new notification instance.
     *
     * @param $project
     */
    public function __construct($project)
    {
        $this->project = $project;
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

        return $this->appendMailUnsubscribe(
            (new MailMessage)
            ->greeting(__('Mail notify greeting'))
            ->subject(__('Mail notify site repaired subject'))
            ->line(__('Mail notify auto disclaimer short'))
            ->line(__('Mail notify site repaired line', ['url' => $this->project->link]))
            ->line(__('Mail notify site broken status', ['code' => $this->project->code]))
            ->line(__('Mail notify site broken uptime', ['percent' => $this->project->uptime_percent]))
            ->action(__('Mail notify check your projects'), route('site.monitoring'))
            ->line(__('Mail notify thanks service')),
            $this->emailPreferenceKey()
        );
    }

    public function emailPreferenceKey(): ?string
    {
        return 'site-mon-repaired';
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
