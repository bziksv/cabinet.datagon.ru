<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchSuggestionsUsage extends Model
{
    protected $table = 'search_suggestions_usages';

    protected $fillable = [
        'user_id',
        'period',
        'used',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
