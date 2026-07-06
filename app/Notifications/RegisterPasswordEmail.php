<?php

namespace App\Notifications;

use App\Notifications\Concerns\LocalizesMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RegisterPasswordEmail extends Notification
{
    use LocalizesMailContent;
    use Queueable;

    private $request;

    private $user;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($request, $user)
    {
        $this->request = $request;
        $this->user = $user;
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
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $this->applyMailLocale($notifiable);

        $password = $this->request->input('password', null);

        return (new MailMessage)
            ->greeting(__('Mail notify greeting'))
            ->subject(__('Mail notify password subject'))
            ->line(__('Mail notify password line'))
            ->line(__('Mail notify password new', ['password' => $password]))
            ->action(__('Mail notify profile action'), url('/profile'))
            ->line(__('Mail notify thanks app'));
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
