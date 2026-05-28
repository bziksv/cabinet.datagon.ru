<?php

namespace App\Services\Database;

use Illuminate\Support\Str;

/**
 * Таблица → Eloquent-модели в app/ (включая public $table).
 */
class EloquentTableMap
{
    /** @var array<string, list<string>>|null */
    private static $cache;

    /**
     * @return array<string, list<string>> table => [ModelClass, ...]
     */
    public static function build(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $map = [];

        foreach (glob(app_path('*.php')) ?: [] as $path) {
            $file = new \SplFileInfo($path);
            $class = 'App\\' . $file->getBasename('.php');

            if (! class_exists($class)) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
            } catch (\ReflectionException $e) {
                continue;
            }

            if ($reflection->isAbstract()) {
                continue;
            }
            if (! $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            $table = self::resolveTableName($reflection);
            if ($table === '') {
                continue;
            }

            $short = $reflection->getShortName();
            if (! isset($map[$table])) {
                $map[$table] = [];
            }
            if (! in_array($short, $map[$table], true)) {
                $map[$table][] = $short;
            }
        }

        if (! isset($map['users'])) {
            $map['users'] = ['User'];
        }

        ksort($map);
        self::$cache = $map;

        return $map;
    }

    private static function resolveTableName(\ReflectionClass $reflection): string
    {
        $defaults = $reflection->getDefaultProperties();
        if (! empty($defaults['table']) && is_string($defaults['table'])) {
            return $defaults['table'];
        }

        if ($reflection->hasProperty('table')) {
            $prop = $reflection->getProperty('table');
            $prop->setAccessible(true);
            try {
                $instance = $reflection->newInstanceWithoutConstructor();
                $value = $prop->getValue($instance);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return Str::snake(Str::pluralStudly($reflection->getShortName()));
    }
}
