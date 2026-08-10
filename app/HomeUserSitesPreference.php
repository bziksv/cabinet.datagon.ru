<?php

namespace App;

use App\Support\HomeUserSites;
use App\Support\SchemaMemo;
use Illuminate\Database\Eloquent\Model;

class HomeUserSitesPreference extends Model
{
    protected $table = 'home_user_sites_preferences';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'columns',
    ];

    protected $casts = [
        'columns' => 'array',
    ];

    public static function tableReady(): bool
    {
        return SchemaMemo::hasTable('home_user_sites_preferences');
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultColumns(): array
    {
        // Суммы 7/30 по умолчанию скрыты — меньше ширина таблицы, есть средние.
        $cols = [
            'modules_count' => true,
            'visits_today' => true,
            'visits_yesterday' => true,
            'visits_sum7' => false,
            'visits_avg7' => true,
            'visits_sum30' => false,
            'visits_avg30' => true,
        ];

        foreach (HomeUserSites::moduleCatalog() as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key !== '') {
                $cols['mod-' . $key] = true;
            }
        }

        return $cols;
    }

    /**
     * @return array<string, bool>
     */
    public static function columnsForUser(int $userId): array
    {
        $defaults = static::defaultColumns();
        if ($userId < 1 || !static::tableReady()) {
            return $defaults;
        }

        $row = static::query()->find($userId);
        $saved = $row && is_array($row->columns) ? $row->columns : [];

        foreach ($defaults as $key => $default) {
            if (array_key_exists($key, $saved)) {
                $defaults[$key] = (bool) $saved[$key];
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $columns
     * @return array<string, bool>
     */
    public static function saveColumns(int $userId, array $columns): array
    {
        $defaults = static::defaultColumns();
        $normalized = [];
        foreach ($defaults as $key => $default) {
            $normalized[$key] = array_key_exists($key, $columns)
                ? (bool) $columns[$key]
                : $default;
        }

        if ($userId > 0 && static::tableReady()) {
            static::query()->updateOrCreate(
                ['user_id' => $userId],
                ['columns' => $normalized]
            );
        }

        return $normalized;
    }
}
