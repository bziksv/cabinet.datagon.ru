<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeUserLock extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'locked_until'];

    protected $dates = ['locked_until'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
