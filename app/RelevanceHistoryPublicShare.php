<?php

namespace App;

use App\Support\RelevancePublicShareTtl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RelevanceHistoryPublicShare extends Model
{
    protected $table = 'relevance_history_public_shares';

    protected $guarded = [];

    protected $dates = [
        'expires_at',
        'revoked_at',
    ];

    public function history(): BelongsTo
    {
        return $this->belongsTo(RelevanceHistory::class, 'history_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isUnlimited(): bool
    {
        return $this->expires_at === null;
    }

    public function expiresLabel(): string
    {
        if ($this->isUnlimited()) {
            return (string) __('Relevance share ttl unlimited');
        }

        return __('Valid until') . ': ' . $this->expires_at->format('d.m.Y H:i');
    }

    public function publicUrl(): string
    {
        return url('/public/share/relevance-check/' . $this->token);
    }

    public static function issueForHistory(RelevanceHistory $history, int $ownerId, $ttlDays = 30): self
    {
        $ttlDays = RelevancePublicShareTtl::normalize($ttlDays);

        static::where('history_id', $history->id)->active()->update([
            'revoked_at' => Carbon::now(),
        ]);

        return static::create([
            'history_id' => $history->id,
            'owner_id' => $ownerId,
            'token' => Str::random(48),
            'ttl_days' => $ttlDays,
            'expires_at' => RelevancePublicShareTtl::resolveExpiresAt($ttlDays),
        ]);
    }

    public static function activeForHistory(int $historyId): ?self
    {
        return static::where('history_id', $historyId)->active()->orderByDesc('id')->first();
    }

    public static function revokeForHistory(int $historyId, int $ownerId): int
    {
        return static::where('history_id', $historyId)
            ->where('owner_id', $ownerId)
            ->active()
            ->update(['revoked_at' => Carbon::now()]);
    }
}
