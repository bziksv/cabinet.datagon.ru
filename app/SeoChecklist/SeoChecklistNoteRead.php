<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SeoChecklistNoteRead extends Model
{
    protected $table = 'seo_checklist_note_reads';

    protected $fillable = [
        'user_id', 'note_id', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('seo_checklist_note_reads');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
