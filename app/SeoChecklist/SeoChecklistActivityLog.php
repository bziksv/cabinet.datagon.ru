<?php

namespace App\SeoChecklist;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class SeoChecklistActivityLog extends Model
{
    protected $table = 'seo_checklist_activity_logs';

    protected $fillable = [
        'project_id', 'item_id', 'user_id', 'type', 'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('seo_checklist_activity_logs');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistProject::class, 'project_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(SeoChecklistActivityRead::class, 'activity_id');
    }
}
