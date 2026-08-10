<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Кэш Schema::hasTable / hasColumn на процесс.
 * На remote MySQL каждый information_schema-запрос = сотни мс;
 * SHOW TABLES один раз дешевле, чем десятки hasTable на главной.
 */
final class SchemaMemo
{
    /** @var array<string, bool> */
    private static $tables = [];

    /** @var array<string, bool> */
    private static $columns = [];

    /** @var bool */
    private static $tablesWarmed = false;

    public static function hasTable(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        if (! array_key_exists($table, self::$tables)) {
            self::warmTables();
        }

        if (array_key_exists($table, self::$tables)) {
            return self::$tables[$table];
        }

        try {
            self::$tables[$table] = Schema::hasTable($table);
        } catch (Throwable $e) {
            self::$tables[$table] = false;
        }

        return self::$tables[$table];
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }

        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columns)) {
            return self::$columns[$key];
        }

        if (! self::hasTable($table)) {
            self::$columns[$key] = false;

            return false;
        }

        try {
            self::$columns[$key] = Schema::hasColumn($table, $column);
        } catch (Throwable $e) {
            self::$columns[$key] = false;
        }

        return self::$columns[$key];
    }

    private static function warmTables(): void
    {
        if (self::$tablesWarmed) {
            return;
        }
        self::$tablesWarmed = true;

        try {
            foreach (DB::select('SHOW TABLES') as $row) {
                $vals = array_values((array) $row);
                $name = isset($vals[0]) ? (string) $vals[0] : '';
                if ($name !== '') {
                    self::$tables[$name] = true;
                }
            }
        } catch (Throwable $e) {
            // fallback: hasTable пойдёт в Schema по одному
        }
    }
}
