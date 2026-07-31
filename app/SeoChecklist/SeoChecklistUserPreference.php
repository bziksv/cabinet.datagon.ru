<?php

namespace App\SeoChecklist;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SeoChecklistUserPreference extends Model
{
    protected $table = 'seo_checklist_user_preferences';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'module_title',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('seo_checklist_user_preferences');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function defaultTitle(): string
    {
        return (string) __('SEO Checklist');
    }

    public static function moduleTitleFor(?int $userId): string
    {
        $default = static::defaultTitle();
        if (!$userId || $userId < 1 || !static::tableReady()) {
            return $default;
        }

        $row = static::query()->find($userId);
        $custom = trim((string) ($row->module_title ?? ''));

        return $custom !== '' ? $custom : $default;
    }

    public static function saveModuleTitle(int $userId, ?string $title): string
    {
        if ($userId < 1 || !static::tableReady()) {
            return static::defaultTitle();
        }

        $title = trim((string) $title);
        if (mb_strlen($title) > 40) {
            $title = mb_substr($title, 0, 40);
        }

        static::query()->updateOrCreate(
            ['user_id' => $userId],
            ['module_title' => $title !== '' ? $title : null]
        );

        return static::moduleTitleFor($userId);
    }
}
