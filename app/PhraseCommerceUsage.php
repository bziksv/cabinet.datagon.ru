<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PhraseCommerceUsage extends Model
{
    protected $table = 'phrase_commerce_usages';

    protected $fillable = [
        'user_id',
        'period',
        'used',
    ];
}
