<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'subject',
        'status',
        'closed_at',
    ];

    protected $dates = [
        'closed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(SupportTicketMessage::class)->latest('created_at');
    }

    public function scopeForStaffInbox($query, string $filter = 'all')
    {
        if ($filter === self::STATUS_OPEN) {
            return $query->where('status', self::STATUS_OPEN);
        }

        if ($filter === self::STATUS_ANSWERED) {
            return $query->where('status', self::STATUS_ANSWERED);
        }

        if ($filter === self::STATUS_CLOSED) {
            return $query->where('status', self::STATUS_CLOSED);
        }

        return $query;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_OPEN => __('Open'),
            self::STATUS_ANSWERED => __('Answered'),
            self::STATUS_CLOSED => __('Closed'),
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        switch ($this->status) {
            case self::STATUS_ANSWERED:
                return 'text-bg-success';
            case self::STATUS_CLOSED:
                return 'text-bg-secondary';
            default:
                return 'text-bg-warning';
        }
    }
}
