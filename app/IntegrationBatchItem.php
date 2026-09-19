<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationBatchItem extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $table = 'integration_batch_items';

    protected $fillable = [
        'batch_id',
        'external_id',
        'url',
        'phrase',
        'status',
        'analysis_public_id',
        'history_id',
        'error',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IntegrationBatch::class, 'batch_id');
    }
}
