<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class SeoChecklistProject extends Model
{
    protected $table = 'seo_checklist_projects';

    protected $fillable = [
        'user_id', 'template_id', 'domain', 'title', 'status',
        'pm_user_id', 'owner_user_id', 'progress_done', 'progress_total', 'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('seo_checklist_projects');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function items(): HasMany
    {
        return $this->hasMany(SeoChecklistItem::class, 'project_id')
            ->orderBy('stage_sort')
            ->orderBy('sort');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistTemplate::class, 'template_id');
    }

    public function recalculateProgress(): void
    {
        $total = $this->items()->whereNull('parent_id')->count();
        $done = $this->items()
            ->whereNull('parent_id')
            ->whereIn('status', ['done', 'skip'])
            ->count();

        $this->forceFill([
            'progress_total' => $total,
            'progress_done' => $done,
            'last_activity_at' => now(),
        ])->save();
    }
}
