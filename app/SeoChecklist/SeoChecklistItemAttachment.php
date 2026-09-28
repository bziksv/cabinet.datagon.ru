<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SeoChecklistItemAttachment extends Model
{
    public const DISK = 'local';

    protected $table = 'seo_checklist_item_attachments';

    protected $fillable = [
        'item_id', 'note_id', 'user_id', 'original_name', 'path', 'mime', 'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /** @var bool|null */
    private static $tableReady;

    public static function tableReady(): bool
    {
        if (self::$tableReady === null) {
            self::$tableReady = Schema::hasTable('seo_checklist_item_attachments');
        }

        return self::$tableReady;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItem::class, 'item_id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistItemNote::class, 'note_id');
    }

    public function extension(): string
    {
        return strtolower((string) pathinfo((string) $this->original_name, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function iconClass(): string
    {
        $ext = $this->extension();
        if ($this->isImage()) {
            return 'bi-file-earmark-image';
        }
        if ($ext === 'pdf') {
            return 'bi-file-earmark-pdf';
        }
        if (in_array($ext, ['doc', 'docx', 'rtf', 'odt'], true)) {
            return 'bi-file-earmark-word';
        }
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'], true)) {
            return 'bi-file-earmark-excel';
        }

        return 'bi-file-earmark-text';
    }

    public function sizeLabel(): string
    {
        $bytes = max(0, (int) $this->size);
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0, ',', ' ') . ' КБ';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', ' ') . ' МБ';
    }
}
