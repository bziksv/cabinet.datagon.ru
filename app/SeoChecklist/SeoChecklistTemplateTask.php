<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoChecklistTemplateTask extends Model
{
    protected $table = 'seo_checklist_template_tasks';

    protected $fillable = [
        'template_id', 'parent_id', 'code', 'stage_key', 'stage_sort', 'sort',
        'title', 'help', 'role', 'is_important', 'allows_subtasks', 'repeat_rule', 'links_json',
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
}
