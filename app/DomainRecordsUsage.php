<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainRecordsUsage extends Model
{
    protected $table = 'domain_records_usages';

    protected $fillable = ['user_id', 'period', 'used'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
