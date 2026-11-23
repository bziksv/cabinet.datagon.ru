<?php

namespace App\Wordstat;

use Illuminate\Database\Eloquent\Model;

class WordstatRunSeed extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'wordstat_run_seeds';
    protected $fillable = ['run_id', 'group_id', 'phrase', 'depth', 'status', 'requests_used', 'error'];
    protected $casts = ['depth' => 'int'];

    public function run()
    {
        return $this->belongsTo(WordstatRun::class, 'run_id');
    }

    public function group()
    {
        return $this->belongsTo(WordstatGroup::class, 'group_id');
    }
}
