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
        'unread_notes',
        'unread_review',
        'unread_created',
    ];

    protected $casts = [
        'unread_notes' => 'boolean',
        'unread_review' => 'boolean',
        'unread_created' => 'boolean',
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

    /**
     * Что показывать во вкладке «Непрочитанные».
     * По умолчанию — только заметки (как раньше).
     *
     * @return array{notes:bool,review:bool,created:bool}
     */
    public static function chronicleUnreadPrefsFor(?int $userId): array
    {
        $defaults = [
            'notes' => true,
            'review' => false,
            'created' => false,
        ];
        if (!$userId || $userId < 1 || !static::tableReady()) {
            return $defaults;
        }
        if (!Schema::hasColumn('seo_checklist_user_preferences', 'unread_notes')) {
            return $defaults;
        }

        $row = static::query()->find($userId);
        if (!$row) {
            return $defaults;
        }

        return [
            'notes' => $row->unread_notes !== null ? (bool) $row->unread_notes : true,
            'review' => (bool) ($row->unread_review ?? false),
            'created' => (bool) ($row->unread_created ?? false),
        ];
    }

    /**
     * @param  array{notes?:bool|int|string,review?:bool|int|string,created?:bool|int|string}  $prefs
     * @return array{notes:bool,review:bool,created:bool}
     */
    public static function saveChronicleUnreadPrefs(int $userId, array $prefs): array
    {
        $normalized = [
            'notes' => !empty($prefs['notes']),
            'review' => !empty($prefs['review']),
            'created' => !empty($prefs['created']),
        ];
        if ($userId < 1 || !static::tableReady()) {
            return $normalized;
        }
        if (!Schema::hasColumn('seo_checklist_user_preferences', 'unread_notes')) {
            return $normalized;
        }

        static::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'unread_notes' => $normalized['notes'],
                'unread_review' => $normalized['review'],
                'unread_created' => $normalized['created'],
            ]
        );

        return static::chronicleUnreadPrefsFor($userId);
    }
}
