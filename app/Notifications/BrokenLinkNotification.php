<?php

namespace App\Notifications;

use App\Contracts\EmailPreferenceAware;
use App\Notifications\Concerns\AppendsMailUnsubscribe;
use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BrokenLinkNotification extends Notification implements EmailPreferenceAware
{
    use AppendsMailUnsubscribe;
    use LocalizesMailContent;
    use Queueable;

    private $request;
    private $link;

    /**
     * Create a new notification instance.
     *
     * @param $request
     * @param $link
     */
    public function __construct($request, $link)
    {
        $this->request = $request;
        $this->link = $link;
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

        return $this->appendMailUnsubscribe(
            (new MailMessage)
            ->greeting(__('Mail notify greeting'))
            ->line(__('Mail notify auto disclaimer'))
            ->line(__('Mail notify backlink donor', ['donor' => $this->link->site_donor]))
            ->line(__('Mail notify backlink link', ['link' => $this->link->link]))
            ->line(__('Mail notify backlink anchor', ['anchor' => $this->link->anchor]))
            ->line(__('Mail notify backlink error', ['error' => $this->request]))
            ->action(__('Mail notify check projects'), route('backlink'))
            ->line(__('Mail notify thanks app')),
            $this->emailPreferenceKey()
        );
    }

    public function emailPreferenceKey(): ?string
    {
        return 'backlink-email-link';
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            //
        ];
    }
}
