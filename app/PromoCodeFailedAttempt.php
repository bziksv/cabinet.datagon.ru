<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeFailedAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'code', 'created_at'];

    protected $dates = ['created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
