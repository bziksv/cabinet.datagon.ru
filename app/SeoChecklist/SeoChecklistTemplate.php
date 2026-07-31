<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoChecklistTemplate extends Model
{
    protected $table = 'seo_checklist_templates';

    protected $fillable = [
        'user_id', 'code', 'title', 'description', 'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(SeoChecklistTemplateTask::class, 'template_id')
            ->orderBy('stage_sort')
            ->orderBy('sort');
    }

    public static function systemDefault(): ?self
    {
        return static::query()->where('code', \App\Support\SeoChecklistDefaultTemplate::CODE)->first();
    }
}
