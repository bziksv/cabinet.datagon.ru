<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Поиск пользователей по ID, email, имени и фамилии (как в списке /users).
 */
class UserSmartSearch
{
    public static function apply(Builder $query, string $search, string $tablePrefix = ''): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $col = static function (string $name) use ($tablePrefix): string {
            return $tablePrefix !== '' ? $tablePrefix . '.' . $name : $name;
        };

        if (ctype_digit($search)) {
            $id = (int) $search;
            $query->where(function ($q) use ($id, $search, $col) {
                $q->where($col('id'), $id)
                    ->orWhere($col('email'), 'like', $search . '%');
            });

            return;
        }

        if (strpos($search, '@') !== false) {
            $query->where($col('email'), 'like', '%' . $search . '%');

            return;
        }

        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $query->where(function ($q) use ($like, $col) {
                $q->where($col('email'), 'like', $like)
                    ->orWhere($col('name'), 'like', $like)
                    ->orWhere($col('last_name'), 'like', $like)
                    ->orWhereRaw('CONCAT(COALESCE(' . $col('name') . ", ''), ' ', COALESCE(" . $col('last_name') . ", '')) LIKE ?", [$like])
                    ->orWhereRaw('CONCAT(COALESCE(' . $col('last_name') . ", ''), ' ', COALESCE(" . $col('name') . ", '')) LIKE ?", [$like]);
            });
        }
    }
}
