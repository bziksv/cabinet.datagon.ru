<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EseninTextCheckUsage extends Model
{
    protected $table = 'esenin_text_check_usages';

    protected $fillable = [
        'user_id',
        'period',
        'used',
    ];
}
