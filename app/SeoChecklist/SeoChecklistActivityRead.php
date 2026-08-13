<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SeoChecklistActivityRead extends Model
{
    protected $table = 'seo_checklist_activity_reads';

    protected $fillable = [
        'user_id', 'activity_id', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('seo_checklist_activity_reads');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
