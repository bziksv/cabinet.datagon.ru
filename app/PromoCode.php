<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    public const BONUS_FIXED = 'fixed';
    public const BONUS_PERCENT = 'percent';

    public const USAGE_ONCE = 'once';
    public const USAGE_MULTI = 'multi';

    public const REDEEM_TOPUP_BONUS = 'topup_bonus';
    public const REDEEM_STANDALONE = 'standalone_credit';

    protected $fillable = [
        'code',
        'title',
        'bonus_type',
        'bonus_value',
        'usage_mode',
        'redeem_mode',
        'max_uses',
        'uses_count',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
        'trigger_campaign_id',
        'assigned_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function triggerCampaign(): BelongsTo
    {
        return $this->belongsTo(TriggerCampaign::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function isStandaloneCredit(): bool
    {
        return $this->redeem_mode === self::REDEEM_STANDALONE;
    }

    public function isTopupBonus(): bool
    {
        return $this->redeem_mode === self::REDEEM_TOPUP_BONUS;
    }

    public function isAssignedToUser(int $userId): bool
    {
        return $this->assigned_user_id === null || (int) $this->assigned_user_id === $userId;
    }

    public function isFixed(): bool
    {
        return $this->bonus_type === self::BONUS_FIXED;
    }

    public function isPercent(): bool
    {
        return $this->bonus_type === self::BONUS_PERCENT;
    }

    public function isOncePerUser(): bool
    {
        return $this->usage_mode === self::USAGE_ONCE;
    }

    public function isMultiUse(): bool
    {
        return $this->usage_mode === self::USAGE_MULTI;
    }

    public function isPerpetual(): bool
    {
        return $this->expires_at === null;
    }

    public function bonusLabel(): string
    {
        if ($this->isPercent()) {
            return $this->bonus_value . '%';
        }

        return number_format($this->bonus_value, 0, '.', ' ') . ' ₽';
    }
}
