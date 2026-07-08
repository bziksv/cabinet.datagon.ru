<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IndexCheckUsage extends Model
{
    protected $fillable = ['user_id', 'period', 'used'];
}
