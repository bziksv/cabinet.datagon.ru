<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistItem extends Model
{
    public const STATUSES = ['todo', 'doing', 'done', 'skip', 'blocked'];

    protected $table = 'seo_checklist_items';

    protected $fillable = [
        'project_id', 'parent_id', 'code', 'stage_key', 'stage_sort', 'sort',
        'title', 'help', 'role', 'is_important', 'allows_subtasks', 'repeat_rule', 'links_json',
        'status', 'assignee_user_id', 'done_at', 'done_by',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'allows_subtasks' => 'boolean',
        'links_json' => 'array',
        'done_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistProject::class, 'project_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SeoChecklistItemNote::class, 'item_id')->orderByDesc('id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }
}
