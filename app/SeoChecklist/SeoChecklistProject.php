<?php

namespace App\SeoChecklist;

use App\Support\SchemaMemo;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistProject extends Model
{
    protected $table = 'seo_checklist_projects';

    protected $fillable = [
        'user_id', 'template_id', 'team_id', 'domain', 'title', 'status', 'skip_weekends',
        'pm_user_id', 'owner_user_id', 'progress_done', 'progress_total', 'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'skip_weekends' => 'boolean',
    ];

    public static function tableReady(): bool
    {
        return SchemaMemo::hasTable('seo_checklist_projects');
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistTeam::class, 'team_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function pmUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pm_user_id');
    }

    public function recalculateProgress(): void
    {
        $total = $this->items()->whereNull('parent_id')->count();
        $done = $this->items()
            ->whereNull('parent_id')
            ->whereIn('status', \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES)
            ->count();

        $this->forceFill([
            'progress_total' => $total,
            'progress_done' => $done,
            'last_activity_at' => now(),
        ])->save();
    }
}
