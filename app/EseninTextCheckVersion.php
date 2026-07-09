<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EseninTextCheckVersion extends Model
{
    protected $table = 'esenin_text_check_versions';

    protected $fillable = [
        'session_id',
        'text',
        'result_json',
        'risk_score',
        'risk_level',
        'is_check',
    ];

    protected $casts = [
        'is_check' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(EseninTextCheckSession::class, 'session_id');
    }
}
