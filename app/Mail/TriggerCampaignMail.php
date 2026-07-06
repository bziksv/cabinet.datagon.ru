<?php

namespace App\Mail;

use App\PromoCode;
use App\Support\MailFromResolver;
use App\Support\MailNotificationFooter;
use App\Support\NotificationLocale;
use App\TriggerCampaign;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TriggerCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var TriggerCampaign */
    public $campaign;

    /** @var User */
    public $user;

    /** @var PromoCode|null */
    public $promoCode;

    /** @var bool */
    public $isTest;

    /** @var string|null */
    public $trackingToken;

    /** @var array<string, mixed>|null */
    public $tariffContext;

    public function __construct(
        TriggerCampaign $campaign,
        User $user,
        ?PromoCode $promoCode,
        bool $isTest = false,
        ?string $trackingToken = null,
        ?array $tariffContext = null
    ) {
        $this->campaign = $campaign;
        $this->user = $user;
        $this->promoCode = $promoCode;
        $this->isTest = $isTest;
        $this->trackingToken = $trackingToken;
        $this->tariffContext = $tariffContext;
    }

    public function build(): self
    {
        $lang = NotificationLocale::apply($this->user);
        $content = $this->campaign->localizedMailContent($lang);

        $subject = $content['subject'];
        if ($this->isTest) {
            $subject = '[TEST] ' . $subject;
        }

        $name = trim(($this->user->name ?? '') . ' ' . ($this->user->last_name ?? ''));
        if ($name === '') {
            $name = (string) $this->user->email;
        }

        $from = MailFromResolver::resolve($lang);

        $trackingPixelUrl = null;
        if (!$this->isTest && $this->trackingToken) {
            $trackingPixelUrl = route('email.trigger.open', ['token' => $this->trackingToken]);
        }

        $ctaUrl = $this->campaign->isTariffExpiringTrigger()
            ? route('tariff.index')
            : ($this->campaign->isRegisteredNoToolTrigger()
                ? route('home')
                : url('/balance'));

        return $this
            ->subject($subject)
            ->from($from['address'], $from['name'])
            ->markdown('emails.trigger-campaign.inactive-return', [
                'mailIntro' => $this->replacePlaceholders((string) $content['intro']),
                'mailBody' => $this->replacePlaceholders((string) $content['body']),
                'userName' => $name,
                'promoCode' => $this->promoCode,
                'showPromo' => $this->campaign->sendsPromo() && $this->promoCode !== null,
                'showActivationGuide' => $this->campaign->isRegisteredNoToolTrigger(),
                'tariffContext' => $this->tariffContext,
                'ctaUrl' => $ctaUrl,
                'ctaLabel' => $this->campaign->isTariffExpiringTrigger()
                    ? __('Trigger campaign email cta tariff')
                    : ($this->campaign->isRegisteredNoToolTrigger()
                        ? __('Trigger campaign email cta activation')
                        : __('Trigger campaign email cta')),
                'isTest' => $this->isTest,
                'trackingPixelUrl' => $trackingPixelUrl,
                'manageNotificationsUrl' => MailNotificationFooter::manageUrl('trigger.' . $this->campaign->slug),
            ]);
    }

    private function replacePlaceholders(string $text): string
    {
        if ($text === '' || $this->tariffContext === null) {
            return $text;
        }

        return strtr($text, [
            ':tariff_name' => (string) ($this->tariffContext['tariff_name'] ?? ''),
            ':active_to' => (string) ($this->tariffContext['active_to'] ?? ''),
            ':expired_at' => (string) ($this->tariffContext['active_to'] ?? ''),
            ':days_left' => (string) ($this->tariffContext['days_left'] ?? ''),
            ':days_since' => (string) ($this->tariffContext['days_since_expiry'] ?? ''),
        ]);
    }
}
