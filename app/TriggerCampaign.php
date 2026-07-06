<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriggerCampaign extends Model
{
    public const TYPE_INACTIVE_DAYS = 'inactive_days';

    public const TYPE_REGISTERED_NO_TOPUP = 'registered_no_topup';

    public const TYPE_REGISTERED_NO_TOOL = 'registered_no_tool';

    public const TYPE_TARIFF_EXPIRING = 'tariff_expiring';

    public const TYPE_TARIFF_EXPIRED = 'tariff_expired';

    public const CHANNEL_CVNG = 'cvng';

    public const DEFAULT_SEND_RATE_PER_MINUTE = 10;

    public const MAX_SEND_RATE_PER_MINUTE = 120;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'is_paused',
        'trigger_type',
        'trigger_days',
        'coupon_bonus_value',
        'coupon_bonus_type',
        'coupon_expires_days',
        'send_rate_per_minute',
        'email_subject',
        'email_intro',
        'email_body',
        'email_subject_en',
        'email_intro_en',
        'email_body_en',
        'channel',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_paused' => 'boolean',
    ];

    public function dispatches(): HasMany
    {
        return $this->hasMany(TriggerCampaignDispatch::class);
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function isInactiveDaysTrigger(): bool
    {
        return $this->trigger_type === self::TYPE_INACTIVE_DAYS;
    }

    public function isRegisteredNoTopupTrigger(): bool
    {
        return $this->trigger_type === self::TYPE_REGISTERED_NO_TOPUP;
    }

    public function isRegisteredNoToolTrigger(): bool
    {
        return $this->trigger_type === self::TYPE_REGISTERED_NO_TOOL;
    }

    public function isTariffExpiringTrigger(): bool
    {
        return $this->trigger_type === self::TYPE_TARIFF_EXPIRING;
    }

    public function isTariffExpiredTrigger(): bool
    {
        return $this->trigger_type === self::TYPE_TARIFF_EXPIRED;
    }

    public function usesTariffPayDispatch(): bool
    {
        return $this->isTariffExpiringTrigger() || $this->isTariffExpiredTrigger();
    }

    public function sendsPromo(): bool
    {
        return !$this->isTariffExpiringTrigger();
    }

    public function couponBonusType(): string
    {
        $type = (string) ($this->coupon_bonus_type ?: PromoCode::BONUS_FIXED);

        return in_array($type, [PromoCode::BONUS_FIXED, PromoCode::BONUS_PERCENT], true)
            ? $type
            : PromoCode::BONUS_FIXED;
    }

    public function isPercentCoupon(): bool
    {
        return $this->couponBonusType() === PromoCode::BONUS_PERCENT;
    }

    public function resolvedPromoRedeemMode(): string
    {
        return $this->isPercentCoupon()
            ? PromoCode::REDEEM_TOPUP_BONUS
            : PromoCode::REDEEM_STANDALONE;
    }

    public function triggerDaysLabelKey(): string
    {
        if ($this->isTariffExpiringTrigger()) {
            return 'Trigger field tariff days before';
        }

        if ($this->isTariffExpiredTrigger()) {
            return 'Trigger field tariff days after';
        }

        if ($this->isRegisteredNoTopupTrigger() || $this->isRegisteredNoToolTrigger()) {
            return 'Trigger field registered days';
        }

        return 'Trigger field inactive days';
    }

    public function isRunning(): bool
    {
        return $this->is_active && !$this->is_paused;
    }

    public function isPaused(): bool
    {
        return $this->is_active && $this->is_paused;
    }

    public function canDispatch(): bool
    {
        return $this->isRunning();
    }

    public function resolvedSendRatePerMinute(): int
    {
        $rate = (int) ($this->send_rate_per_minute ?: self::DEFAULT_SEND_RATE_PER_MINUTE);

        return max(1, min(self::MAX_SEND_RATE_PER_MINUTE, $rate));
    }

    public function nextInactiveTierDays(): ?int
    {
        $next = static::query()
            ->where('trigger_type', self::TYPE_INACTIVE_DAYS)
            ->where('trigger_days', '>', (int) $this->trigger_days)
            ->min('trigger_days');

        return $next !== null ? (int) $next : null;
    }

    public function nextRegisteredNoTopupTierDays(): ?int
    {
        $next = static::query()
            ->where('trigger_type', self::TYPE_REGISTERED_NO_TOPUP)
            ->where('trigger_days', '>', (int) $this->trigger_days)
            ->min('trigger_days');

        return $next !== null ? (int) $next : null;
    }

    public function nextRegisteredNoToolTierDays(): ?int
    {
        $next = static::query()
            ->where('trigger_type', self::TYPE_REGISTERED_NO_TOOL)
            ->where('trigger_days', '>', (int) $this->trigger_days)
            ->min('trigger_days');

        return $next !== null ? (int) $next : null;
    }

    public function nextTariffExpiredTierDays(): ?int
    {
        $next = static::query()
            ->where('trigger_type', self::TYPE_TARIFF_EXPIRED)
            ->where('trigger_days', '>', (int) $this->trigger_days)
            ->min('trigger_days');

        return $next !== null ? (int) $next : null;
    }

    /** @var list<string> */
    private const ADMIN_LIST_PRIORITY_SLUGS = [
        'registered_no_topup_7_days',
        'inactive_30_days',
        'inactive_90_days',
        'inactive_180_days',
    ];

    /** @var array<string, int> */
    private const ADMIN_LIST_TYPE_ORDER = [
        self::TYPE_REGISTERED_NO_TOOL => 300,
        self::TYPE_TARIFF_EXPIRING => 400,
        self::TYPE_TARIFF_EXPIRED => 500,
    ];

    public static function compareForAdminList(self $a, self $b): int
    {
        $ia = array_search($a->slug, self::ADMIN_LIST_PRIORITY_SLUGS, true);
        $ib = array_search($b->slug, self::ADMIN_LIST_PRIORITY_SLUGS, true);
        $ia = $ia === false ? PHP_INT_MAX : $ia;
        $ib = $ib === false ? PHP_INT_MAX : $ib;

        if ($ia !== $ib) {
            return $ia <=> $ib;
        }

        $typeOrder = self::ADMIN_LIST_TYPE_ORDER;
        $typeCompare = ($typeOrder[$a->trigger_type] ?? 900) <=> ($typeOrder[$b->trigger_type] ?? 900);
        if ($typeCompare !== 0) {
            return $typeCompare;
        }

        if ($a->trigger_type === self::TYPE_TARIFF_EXPIRING) {
            return (int) $b->trigger_days <=> (int) $a->trigger_days;
        }

        $daysCompare = (int) $a->trigger_days <=> (int) $b->trigger_days;

        return $daysCompare !== 0 ? $daysCompare : ($a->id <=> $b->id);
    }

    /**
     * @return list<self>
     */
    public static function allOrderedForAdmin(): array
    {
        $campaigns = static::query()->get()->all();
        usort($campaigns, [static::class, 'compareForAdminList']);

        return $campaigns;
    }

    /**
     * Краткое описание для профиля (без промо/админских деталей).
     */
    public function profileDescription(): string
    {
        $key = 'Trigger campaign profile desc.' . $this->slug;
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return (string) $this->name;
    }

    /**
     * @return array{subject: string, intro: ?string, body: ?string}
     */
    public function localizedMailContent(string $lang): array
    {
        if ($lang === 'en') {
            return [
                'subject' => trim((string) ($this->email_subject_en ?: $this->email_subject)),
                'intro' => $this->email_intro_en ?: $this->email_intro,
                'body' => $this->email_body_en ?: $this->email_body,
            ];
        }

        return [
            'subject' => (string) $this->email_subject,
            'intro' => $this->email_intro,
            'body' => $this->email_body,
        ];
    }
}
