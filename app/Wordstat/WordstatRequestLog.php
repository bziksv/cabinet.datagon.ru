<?php

namespace App\Wordstat;

use Illuminate\Database\Eloquent\Model;

class WordstatRequestLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'wordstat_request_log';
    protected $fillable = ['user_id', 'project_id', 'run_id', 'kind', 'created_at'];
}
