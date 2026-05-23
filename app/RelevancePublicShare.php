<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RelevancePublicShare extends Model
{
    public const TTL_DAYS = 30;

    protected $table = 'relevance_public_shares';

    protected $guarded = [];

    protected $dates = [
        'expires_at',
        'revoked_at',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectRelevanceHistory::class, 'project_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function publicUrl(): string
    {
        return url('/public/share/relevance/' . $this->token);
    }

    public static function issueForProject(ProjectRelevanceHistory $project, int $ownerId): self
    {
        static::where('project_id', $project->id)->active()->update([
            'revoked_at' => Carbon::now(),
        ]);

        return static::create([
            'project_id' => $project->id,
            'owner_id' => $ownerId,
            'token' => Str::random(48),
            'expires_at' => Carbon::now()->addDays(static::TTL_DAYS),
        ]);
    }

    public static function revokeForProject(int $projectId, int $ownerId): int
    {
        return static::where('project_id', $projectId)
            ->where('owner_id', $ownerId)
            ->active()
            ->update(['revoked_at' => Carbon::now()]);
    }
}
