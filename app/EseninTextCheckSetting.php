<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EseninTextCheckSetting extends Model
{
    protected $table = 'esenin_text_check_settings';

    protected $fillable = [
        'code',
        'value',
    ];
}
