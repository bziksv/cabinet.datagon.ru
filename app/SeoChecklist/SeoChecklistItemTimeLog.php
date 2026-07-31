<?php

namespace App\SeoChecklist;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoChecklistItemTimeLog extends Model
{
    protected $table = 'seo_checklist_item_time_logs';

    protected $fillable = [
        'item_id', 'user_id', 'work_date', 'started_at', 'ended_at', 'duration_seconds',
    ];

    protected $casts = [
        'work_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function elapsedSeconds(?\DateTimeInterface $now = null): int
    {
        if ($this->ended_at && $this->duration_seconds !== null) {
            return max(0, (int) $this->duration_seconds);
        }

        $start = $this->started_at;
        if (!$start) {
            return 0;
        }

        $end = $this->ended_at ?: ($now ? \Carbon\Carbon::instance($now) : now());

        return max(0, (int) $start->diffInSeconds($end));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
