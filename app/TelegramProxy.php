<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TelegramProxy extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'telegram_proxies';

    protected $fillable = [
        'id',
        'label',
        'supplier',
        'url',
        'priority',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];
}
