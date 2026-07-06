<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Support\SmtpSettingsRegistry;
use App\Support\MailFromResolver;
use App\Support\NotificationDispatchLogger;
use App\Services\UserNotificationPreferenceService;
use App\Contracts\EmailPreferenceAware;
use App\TriggerCampaign;
use App\Observers\TriggerCampaignObserver;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationSending;
use App\User;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment('local') && ($root = config('app.url'))) {
            URL::forceRootUrl(rtrim($root, '/'));
        }

        Validator::extend('website', function ($attribute, $value) {
            if (isset($value)) {
                if (!preg_match("~^(?:f|ht)tps?://~i", $value)) {
                    $value = "https://" . $value;
                }

                $link = parse_url($value);

                if (isset($link['host'])) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }, __('Invalid link.'));

        Validator::extend('not_website', function ($attribute, $value) {
            $link = parse_url($value);
            if (!isset($link['host'])) {
                return true;
            } else {
                return false;
            }
        }, __('The phrase cannot be a link.'));

        Schema::defaultStringLength(191);

        Event::listen(MessageSending::class, function (MessageSending $event) {
            $from = MailFromResolver::resolve();
            if ($from['address'] === '') {
                return;
            }

            $event->message->setFrom([$from['address'] => $from['name']]);
        });

        Event::listen(NotificationSending::class, function (NotificationSending $event) {
            if ($event->channel !== 'mail') {
                return;
            }

            if (!$event->notification instanceof EmailPreferenceAware) {
                return;
            }

            $key = $event->notification->emailPreferenceKey();
            if ($key === null || $key === '') {
                return;
            }

            if (!$event->notifiable instanceof User) {
                return;
            }

            if (!app(UserNotificationPreferenceService::class)->isEnabled($event->notifiable, $key)) {
                return false;
            }
        });

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel !== 'mail') {
                return;
            }

            $eventId = NotificationDispatchLogger::resolveEmailEventId(
                get_class($event->notification),
                $event->notification
            );
            if ($eventId === null) {
                return;
            }

            $userId = $event->notifiable instanceof User ? (int) $event->notifiable->id : null;
            NotificationDispatchLogger::log($eventId, NotificationDispatchLogger::CHANNEL_EMAIL, $userId);
        });

        try {
            if (Schema::hasTable('smtp_settings')) {
                SmtpSettingsRegistry::applyToConfig();
            }
        } catch (\Throwable $e) {
            // БД может быть недоступна при первой установке
        }

        TriggerCampaign::observe(TriggerCampaignObserver::class);
    }
}
