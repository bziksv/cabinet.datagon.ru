<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureIdeaVote extends Model
{
    protected $fillable = [
        'feature_idea_id',
        'user_id',
    ];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(FeatureIdea::class, 'feature_idea_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
