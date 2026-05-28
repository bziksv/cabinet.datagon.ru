<?php

namespace App\Services\Database;

class TableModuleResolver
{
    /**
     * @return list<array{title:string,uri:string,note?:string}>
     */
    public static function resolve(string $table): array
    {
        $modules = [];
        $exact = config('cabinet-database-admin.table_modules.' . $table);
        if (is_array($exact) && ! empty($exact['title'])) {
            $modules[] = self::normalizeModule($exact);
        }

        if ($modules !== []) {
            return self::uniqueModules($modules);
        }

        $prefixes = config('cabinet-database-admin.prefix_modules', []);
        foreach ($prefixes as $prefix => $module) {
            if (strpos($table, $prefix) === 0 && is_array($module)) {
                $modules[] = self::normalizeModule($module);
                break;
            }
        }

        return self::uniqueModules($modules);
    }

    public static function isSystemTable(string $table): bool
    {
        return array_key_exists($table, config('cabinet-database-admin.system_tables', []));
    }

    /**
     * @return array{category:string,title:string}|null
     */
    public static function systemMeta(string $table): ?array
    {
        $meta = config('cabinet-database-admin.system_tables.' . $table);

        return is_array($meta) ? $meta : null;
    }

    /**
     * @param array{title?:string,uri?:string,note?:string} $module
     * @return array{title:string,uri:string,note?:string}
     */
    private static function normalizeModule(array $module): array
    {
        $out = [
            'title' => (string) ($module['title'] ?? '—'),
            'uri' => (string) ($module['uri'] ?? ''),
        ];
        if (! empty($module['note'])) {
            $out['note'] = (string) $module['note'];
        }

        return $out;
    }

    /**
     * @param list<array{title:string,uri:string,note?:string}> $modules
     * @return list<array{title:string,uri:string,note?:string}>
     */
    private static function uniqueModules(array $modules): array
    {
        $seen = [];
        $out = [];
        foreach ($modules as $m) {
            $key = $m['title'] . '|' . $m['uri'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $m;
        }

        return $out;
    }
}
