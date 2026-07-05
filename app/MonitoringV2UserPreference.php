<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MonitoringV2UserPreference extends Model
{
    protected $table = 'monitoring_v2_user_preferences';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'list_columns',
    ];

    protected $casts = [
        'list_columns' => 'array',
    ];

    public static function defaultListColumns(): array
    {
        return [
            'top3' => true,
            'top5' => true,
            'top10' => true,
            'top30' => true,
            'top100' => true,
            'middle' => true,
            'words' => true,
            'users' => true,
            'engines' => true,
            'budget' => true,
            'mastered' => true,
        ];
    }

    public static function listColumnsForUser(int $userId): array
    {
        $row = static::query()->find($userId);
        $defaults = static::defaultListColumns();
        $saved = $row && is_array($row->list_columns) ? $row->list_columns : [];

        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $saved)) {
                $defaults[$key] = (bool) $saved[$key];
            }
        }

        return $defaults;
    }

    public static function saveListColumns(int $userId, array $columns): array
    {
        $defaults = static::defaultListColumns();
        $normalized = [];
        foreach ($defaults as $key => $default) {
            $normalized[$key] = array_key_exists($key, $columns)
                ? (bool) $columns[$key]
                : $default;
        }

        static::query()->updateOrCreate(
            ['user_id' => $userId],
            ['list_columns' => $normalized]
        );

        return $normalized;
    }
}
