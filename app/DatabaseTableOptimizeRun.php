<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DatabaseTableOptimizeRun extends Model
{
    protected $table = 'database_table_optimize_runs';

    protected $fillable = [
        'table_name',
        'status',
        'mode',
        'triggered_by',
        'size_before_mb',
        'size_after_mb',
        'freed_mb',
        'data_free_before_mb',
        'message',
        'started_at',
        'finished_at',
    ];

    protected $dates = [
        'started_at',
        'finished_at',
        'created_at',
        'updated_at',
    ];
}
