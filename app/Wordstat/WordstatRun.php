<?php

namespace App\Wordstat;

use Illuminate\Database\Eloquent\Model;

class WordstatRun extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'wordstat_runs';
    protected $fillable = [
        'project_id', 'user_id', 'settings', 'status', 'seeds_total', 'seeds_done', 'requests_estimated',
        'requests_used', 'phrases_added', 'error', 'started_at', 'finished_at',
    ];
    protected $casts = ['settings' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(WordstatProject::class, 'project_id');
    }

    public function seeds()
    {
        return $this->hasMany(WordstatRunSeed::class, 'run_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }
}
