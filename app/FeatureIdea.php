<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureIdea extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'status',
        'votes_count',
        'moderated_by',
        'approved_at',
        'rejected_at',
        'moderator_note',
    ];

    protected $dates = [
        'approved_at',
        'rejected_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(FeatureIdeaVote::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function statusBadgeClass(): string
    {
        switch ($this->status) {
            case self::STATUS_APPROVED:
                return 'text-bg-success';
            case self::STATUS_REJECTED:
                return 'text-bg-secondary';
            default:
                return 'text-bg-warning';
        }
    }

    public function statusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => __('Under moderation'),
            self::STATUS_APPROVED => __('On the board'),
            self::STATUS_REJECTED => __('Declined'),
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function authorDisplayName(): string
    {
        $user = $this->user;

        if (!$user) {
            return __('User');
        }

        $name = trim((string) ($user->fullName ?? ''));

        return $name !== '' ? $name : (string) $user->email;
    }
}
