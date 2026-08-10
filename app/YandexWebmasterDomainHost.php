<?php

namespace App;

use App\Support\HomeUserSites;
use App\Support\SchemaMemo;
use Illuminate\Database\Eloquent\Model;

class YandexWebmasterDomainHost extends Model
{
    protected $table = 'yandex_webmaster_domain_hosts';

    protected $fillable = [
        'user_id',
        'domain',
        'host_id',
        'host_url',
        'verified',
    ];

    protected $casts = [
        'verified' => 'boolean',
    ];

    public static function tableReady(): bool
    {
        return SchemaMemo::hasTable('yandex_webmaster_domain_hosts');
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

    public static function bind(
        int $userId,
        string $rawDomain,
        string $hostId,
        ?string $hostUrl = null,
        bool $verified = false
    ): ?self {
        if ($userId < 1 || !self::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        $hostId = trim($hostId);
        if ($domain === '' || $hostId === '') {
            return null;
        }

        return self::query()->updateOrCreate(
            ['user_id' => $userId, 'domain' => $domain],
            [
                'host_id' => $hostId,
                'host_url' => $hostUrl,
                'verified' => $verified,
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
