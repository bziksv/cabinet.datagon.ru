<?php

namespace App\Notifications;

use App\Contracts\EmailPreferenceAware;
use App\Notifications\Concerns\AppendsMailUnsubscribe;
use App\Notifications\Concerns\LocalizesMailContent;
use App\Services\TelegramBotService;
use App\SiteAuditCrawl;
use App\Support\NotificationLocale;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteAuditCrawlCompletedNotification extends Notification implements EmailPreferenceAware
{
    use AppendsMailUnsubscribe;
    use LocalizesMailContent;
    use Queueable;

    /** @var SiteAuditCrawl */
    public $crawl;

    public function __construct(SiteAuditCrawl $crawl)
    {
        $this->crawl = $crawl;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $this->applyMailLocale($notifiable);
        $this->crawl->loadMissing('project');

        $domain = optional($this->crawl->project)->domain ?? '—';
        $summary = $this->bucketsSummary();

        return $this->appendMailUnsubscribe(
            (new MailMessage)
                ->subject(__('Site audit crawl done mail subject', ['domain' => $domain]))
                ->greeting(__('Mail notify greeting'))
                ->line(__('Site audit crawl done mail line', [
                    'domain' => $domain,
                    'id' => $this->crawl->id,
                ]))
                ->line(__('Site audit crawl done mail pages', [
                    'fetched' => (int) $this->crawl->pages_fetched,
                    'total' => (int) $this->crawl->pages_total,
                ]))
                ->line($summary)
                ->action(__('Site audit crawl done mail action'), route('pages.site-audit.crawl.show', $this->crawl->id))
                ->line(__('Mail notify thanks service')),
            $this->emailPreferenceKey()
        );
    }

    public function emailPreferenceKey(): ?string
    {
        return 'site-audit-crawl-done';
    }

    /**
     * Telegram владельцу, если бот подключён.
     */
    public static function sendTelegram(User $user, SiteAuditCrawl $crawl, string $source = 'system'): bool
    {
        if (! $user->isTelegramConnected() || empty(config('app.telegram_bot_token'))) {
            return false;
        }

        NotificationLocale::apply($user);

        $crawl->loadMissing('project');
        $domain = optional($crawl->project)->domain ?? '—';
        $buckets = $crawl->buckets_json ?: [];
        $url = route('pages.site-audit.crawl.show', $crawl->id);
        $moduleUrl = route('pages.site-audit');

        $text = __('Site audit crawl done telegram body', [
            'domain' => $domain,
            'id' => $crawl->id,
            'fetched' => (int) $crawl->pages_fetched,
            'total' => (int) $crawl->pages_total,
            'critical' => (int) ($buckets['critical'] ?? 0),
            'other' => (int) ($buckets['other'] ?? 0),
            'important' => (int) ($buckets['important'] ?? 0),
            'warning' => (int) ($buckets['warning'] ?? 0),
            'info' => (int) ($buckets['info'] ?? 0),
            'module_url' => $moduleUrl,
            'url' => $url,
        ]);

        $markup = null;
        if (TelegramBotService::supportsInlineUrlButton($url)) {
            $markup = [
                'inline_keyboard' => [[
                    ['text' => __('Site audit crawl done telegram button'), 'url' => $url],
                ]],
            ];
        }

        return (new TelegramBotService((int) $user->chat_id))->sendMsg($text, $markup, [
            'event_id' => 'site-audit-crawl-done',
            'user_id' => (int) $user->id,
            'source' => $source,
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'crawl_id' => $this->crawl->id,
        ];
    }

    private function bucketsSummary(): string
    {
        $buckets = $this->crawl->buckets_json ?: [];

        return __('Site audit crawl done mail buckets', [
            'critical' => (int) ($buckets['critical'] ?? 0),
            'other' => (int) ($buckets['other'] ?? 0),
            'important' => (int) ($buckets['important'] ?? 0),
            'warning' => (int) ($buckets['warning'] ?? 0),
            'info' => (int) ($buckets['info'] ?? 0),
        ]);
    }
}
