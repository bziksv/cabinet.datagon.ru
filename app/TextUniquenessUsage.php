<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TextUniquenessUsage extends Model
{
    protected $fillable = [
        'user_id',
        'period',
        'used',
    ];
}
