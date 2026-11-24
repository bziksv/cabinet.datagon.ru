<?php

namespace App\Services\Wordstat;

use App\MonitoringSettings;
use Illuminate\Support\Facades\Cache;

class WordstatRateLimiter
{
    public const SETTING = 'wordstat_rps';
    private const CACHE_KEY = 'wordstat.rps';

    public static function limit(): int
    {
        return (int) Cache::remember(self::CACHE_KEY, 15, function () {
            $raw = (new MonitoringSettings())->getValue(self::SETTING);
            $value = $raw === false || $raw === null || $raw === '' ? (int) config('cabinet-wordstat-keywords.rps_default', 5) : (int) $raw;

            return max(1, min(100, $value));
        });
    }

    public static function acquire(): void
    {
        $rps = self::limit();
        $path = storage_path('framework/wordstat-rps.json');
        $deadline = microtime(true) + 120;

        while (microtime(true) < $deadline) {
            $fp = fopen($path, 'c+');
            if ($fp === false) {
                usleep(50000);
                continue;
            }
            try {
                if (! flock($fp, LOCK_EX)) {
                    usleep(20000);
                    continue;
                }
                $state = json_decode(stream_get_contents($fp) ?: '', true);
                $window = time();
                if (! is_array($state) || (int) ($state['window'] ?? 0) !== $window) {
                    $state = ['window' => $window, 'count' => 0];
                }
                if ((int) $state['count'] < $rps) {
                    $state['count'] = (int) $state['count'] + 1;
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode($state));
                    fflush($fp);

                    return;
                }
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            usleep(100000);
        }

        // Не отправляем запрос сверх лимита: вернём задачу в очередь на повтор
        throw WordstatException::retryable('rate limit wait');
    }
}
