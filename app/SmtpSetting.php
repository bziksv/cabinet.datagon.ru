<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SmtpSetting extends Model
{
    public const PRIMARY_ID = 'default';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'smtp_settings';

    protected $fillable = [
        'id',
        'enabled',
        'provider_label',
        'driver',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'from_name_en',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'port' => 'integer',
    ];
}
