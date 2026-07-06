<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriggerCampaignDispatch extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REDEEMED = 'redeemed';

    protected $fillable = [
        'trigger_campaign_id',
        'user_id',
        'dispatch_key',
        'promo_code_id',
        'tariff_pay_id',
        'tracking_token',
        'status',
        'is_test',
        'queued_at',
        'sent_at',
        'opened_at',
        'open_count',
        'redeemed_at',
        'balance_id',
        'last_error',
    ];

    protected $casts = [
        'is_test' => 'boolean',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TriggerCampaign::class, 'trigger_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function tariffPay(): BelongsTo
    {
        return $this->belongsTo(TariffPay::class);
    }

    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }

    public function isRedeemed(): bool
    {
        return $this->status === self::STATUS_REDEEMED || $this->redeemed_at !== null;
    }

    public function isOpened(): bool
    {
        return $this->opened_at !== null;
    }

    public function wasSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_REDEEMED], true);
    }
}
