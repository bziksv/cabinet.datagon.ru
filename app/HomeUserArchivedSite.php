<?php

namespace App;

use App\Support\HomeUserSites;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class HomeUserArchivedSite extends Model
{
    public const KIND_ARCHIVED = 'archived';
    public const KIND_HIDDEN = 'hidden';

    protected $table = 'home_user_archived_sites';

    protected $fillable = [
        'user_id',
        'domain',
        'kind',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('home_user_archived_sites');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasKindColumn(): bool
    {
        try {
            return self::tableReady() && Schema::hasColumn('home_user_archived_sites', 'kind');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, true>
     */
    public static function domainMapForUser(int $userId, string $kind = self::KIND_ARCHIVED): array
    {
        if ($userId < 1 || !self::tableReady()) {
            return [];
        }

        $q = self::query()->where('user_id', $userId);
        if (self::hasKindColumn()) {
            $q->where('kind', $kind);
        } elseif ($kind !== self::KIND_ARCHIVED) {
            return [];
        }

        $map = [];
        $q->orderBy('domain')
            ->pluck('domain')
            ->each(static function ($domain) use (&$map) {
                $host = HomeUserSites::normalizeDomain((string) $domain);
                if ($host !== '') {
                    $map[$host] = true;
                }
            });

        return $map;
    }

    public static function setForUser(int $userId, string $rawDomain, string $kind): ?string
    {
        if ($userId < 1 || !self::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '') {
            return null;
        }

        $kind = $kind === self::KIND_HIDDEN ? self::KIND_HIDDEN : self::KIND_ARCHIVED;

        if (self::hasKindColumn()) {
            self::query()->updateOrCreate(
                ['user_id' => $userId, 'domain' => $domain],
                ['kind' => $kind]
            );
        } else {
            self::query()->firstOrCreate([
                'user_id' => $userId,
                'domain' => $domain,
            ]);
        }

        return $domain;
    }

    public static function archiveForUser(int $userId, string $rawDomain): ?string
    {
        return self::setForUser($userId, $rawDomain, self::KIND_ARCHIVED);
    }

    public static function hideForUser(int $userId, string $rawDomain): ?string
    {
        return self::setForUser($userId, $rawDomain, self::KIND_HIDDEN);
    }

    public static function restoreForUser(int $userId, string $rawDomain): ?string
    {
        if ($userId < 1 || !self::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($rawDomain);
        if ($domain === '') {
            return null;
        }

        self::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->delete();

        return $domain;
    }
}
