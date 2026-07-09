<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EseninTextCheckSession extends Model
{
    protected $table = 'esenin_text_check_sessions';

    protected $fillable = [
        'user_id',
        'name',
        'source',
        'source_url',
        'tbclass',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(EseninTextCheckVersion::class, 'session_id');
    }
}
