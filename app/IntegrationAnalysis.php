<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationAnalysis extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $table = 'integration_analyses';

    protected $fillable = [
        'public_id',
        'user_id',
        'api_key_id',
        'progress_hash',
        'history_id',
        'status',
        'url',
        'phrase',
        'request_payload',
        'error',
    ];

    protected $casts = [
        'request_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(IntegrationApiKey::class, 'api_key_id');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(RelevanceHistory::class, 'history_id');
    }
}
