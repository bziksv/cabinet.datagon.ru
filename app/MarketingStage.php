<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Этап плана «Маркетинг сайта» (titlo.ru).
 */
class MarketingStage extends Model
{
    protected $table = 'marketing_stages';

    protected $guarded = [];

    public function tasks()
    {
        return $this->hasMany(MarketingTask::class, 'stage_id')->orderBy('sort')->orderBy('id');
    }

    /** @return array{total:int,done:int,percent:int} */
    public function progress(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        $active = $tasks->where('status', '!=', 'postponed');
        $total = $active->count();
        $done = $active->where('status', 'done')->count();

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total > 0 ? (int) round($done * 100 / $total) : ($this->status === 'done' ? 100 : 0),
        ];
    }
}
