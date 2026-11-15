<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Задача этапа «Маркетинг сайта».
 */
class MarketingTask extends Model
{
    protected $table = 'marketing_tasks';

    protected $guarded = [];

    protected $dates = ['done_at'];

    public function stage()
    {
        return $this->belongsTo(MarketingStage::class, 'stage_id');
    }

    /** @return array<int, string> */
    public function linkList(): array
    {
        return MarketingContentItem::splitLines((string) $this->links);
    }
}
