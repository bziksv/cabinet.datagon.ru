<?php

namespace App\Services\SiteAudit;

/**
 * Временные HTML body краула: Guzzle sink на диск, удаление сразу после parse.
 * Защита от разрастания: TTL + лимит суммарного размера и числа файлов.
 */
class SiteAuditBodyTemp
{
    public static function enabled(): bool
    {
        return (bool) config('site_audit.body_tempfile_enabled', true);
    }

    public static function dir(): string
    {
        $dir = (string) config(
            'site_audit.body_temp_path',
            storage_path('app/site-audit-body-tmp')
        );
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        return $dir;
    }

    /**
     * Путь под новый sink-файл. Перед аллокацией — лёгкий prune.
     */
    public static function allocate(?int $crawlId = null): string
    {
        self::prune();
        $dir = self::dir();
        $cid = $crawlId !== null ? (int) $crawlId : 0;
        $name = sprintf(
            'sa-body-%d-%s-%s.html',
            $cid,
            date('YmdHis'),
            bin2hex(random_bytes(4))
        );
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        @touch($path);

        return $path;
    }

    public static function release(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $dir = realpath(self::dir());
        $real = realpath($path);
        if ($dir && $real && strpos($real, $dir) === 0 && is_file($real)) {
            @unlink($real);

            return;
        }
        if (is_file($path) && strpos(basename($path), 'sa-body-') === 0) {
            $parent = realpath(dirname($path));
            if ($dir && $parent && $parent === $dir) {
                @unlink($path);
            }
        }
    }

    public static function read(string $path, ?int $maxBytes = null): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }
        $max = $maxBytes !== null
            ? max(1, $maxBytes)
            : max(1, (int) config('site_audit.large_page_bytes', 1_500_000));
        $size = (int) filesize($path);
        if ($size <= 0) {
            return '';
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return null;
        }
        try {
            $data = fread($fh, min($size, $max));

            return $data === false ? null : $data;
        } finally {
            fclose($fh);
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    public static function takeBody(array $result): ?string
    {
        if (isset($result['body']) && is_string($result['body'])) {
            return $result['body'];
        }
        $path = isset($result['body_path']) ? (string) $result['body_path'] : '';
        if ($path === '') {
            return null;
        }

        return self::read($path);
    }

    /**
     * @return array{deleted:int,kept:int,bytes:int}
     */
    public static function prune(?int $onlyCrawlId = null, bool $force = false): array
    {
        $dir = self::dir();
        if (! is_dir($dir)) {
            return ['deleted' => 0, 'kept' => 0, 'bytes' => 0];
        }

        $maxAge = max(60, (int) config('site_audit.body_temp_max_age_sec', 1800));
        $maxBytes = max(10_000_000, (int) config('site_audit.body_temp_max_total_bytes', 200_000_000));
        $maxFiles = max(10, (int) config('site_audit.body_temp_max_files', 200));
        $now = time();

        $files = [];
        $dh = @opendir($dir);
        if ($dh === false) {
            return ['deleted' => 0, 'kept' => 0, 'bytes' => 0];
        }
        while (($name = readdir($dh)) !== false) {
            if ($name === '.' || $name === '..' || strpos($name, 'sa-body-') !== 0) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (! is_file($path)) {
                continue;
            }
            if ($onlyCrawlId !== null && ! preg_match('/^sa-body-' . (int) $onlyCrawlId . '-/', $name)) {
                continue;
            }
            $files[] = [
                'path' => $path,
                'mtime' => (int) @filemtime($path),
                'size' => (int) @filesize($path),
            ];
        }
        closedir($dh);

        $deleted = 0;
        $keep = [];

        // Режим «весь краул» или force — снести всё из выборки.
        if ($force || $onlyCrawlId !== null) {
            foreach ($files as $f) {
                if (@unlink($f['path'])) {
                    $deleted++;
                }
            }

            return ['deleted' => $deleted, 'kept' => 0, 'bytes' => 0];
        }

        foreach ($files as $f) {
            if (($now - $f['mtime']) > $maxAge) {
                if (@unlink($f['path'])) {
                    $deleted++;
                }
                continue;
            }
            $keep[] = $f;
        }

        usort($keep, static function ($a, $b) {
            return $a['mtime'] <=> $b['mtime'];
        });

        $total = 0;
        foreach ($keep as $f) {
            $total += $f['size'];
        }
        while ((count($keep) > $maxFiles || $total > $maxBytes) && $keep !== []) {
            $victim = array_shift($keep);
            $total -= (int) $victim['size'];
            if (@unlink($victim['path'])) {
                $deleted++;
            }
        }

        $bytes = 0;
        foreach ($keep as $f) {
            $bytes += (int) $f['size'];
        }

        return [
            'deleted' => $deleted,
            'kept' => count($keep),
            'bytes' => $bytes,
        ];
    }
}
