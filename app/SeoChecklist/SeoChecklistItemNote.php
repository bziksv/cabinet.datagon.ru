<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoChecklistItemNote extends Model
{
    protected $table = 'seo_checklist_item_notes';

    protected $fillable = [
        'item_id', 'user_id', 'body',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItem::class, 'item_id');
    }
}
