<?php

namespace App;

use App\Support\HomeUserSites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class YandexMetrikaDomainCounter extends Model
{
    protected $table = 'yandex_metrika_domain_counters';

    protected $fillable = [
        'user_id',
        'domain',
        'counter_id',
        'counter_name',
        'counter_site',
    ];

    protected $casts = [
        'counter_id' => 'integer',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('yandex_metrika_domain_counters');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function forUser(int $userId)
    {
        if ($userId < 1 || !self::tableReady()) {
            return collect();
        }

        return self::query()
            ->where('user_id', $userId)
            ->orderBy('domain')
            ->get();
    }

    public static function bind(int $userId, string $rawDomain, int $counterId, ?string $name = null, ?string $site = null): ?self
    {
        if ($userId < 1 || !self::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '' || $counterId < 1) {
            return null;
        }

        return self::query()->updateOrCreate(
            ['user_id' => $userId, 'domain' => $domain],
            [
                'counter_id' => $counterId,
                'counter_name' => $name,
                'counter_site' => $site,
            ]
        );
    }

    public static function unbind(int $userId, string $rawDomain): bool
    {
        if ($userId < 1 || !self::tableReady()) {
            return false;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '') {
            return false;
        }

        return (bool) self::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->delete();
    }
}
