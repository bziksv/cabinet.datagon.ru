<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistTemplateTask extends Model
{
    protected $table = 'seo_checklist_template_tasks';

    protected $fillable = [
        'template_id', 'parent_id', 'code', 'stage_key', 'stage_sort', 'sort',
        'title', 'help', 'role', 'is_important', 'allows_subtasks', 'repeat_rule', 'due_days_from_start', 'links_json',
    ];

    protected $casts = [
        'is_important' => 'boolean',
        'allows_subtasks' => 'boolean',
        'links_json' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistTemplate::class, 'template_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }
}
