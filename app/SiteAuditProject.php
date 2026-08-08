<?php

namespace App;

use App\SeoChecklist\SeoChecklistTeam;
use App\SeoChecklist\SeoChecklistTeamMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SiteAuditProject extends Model
{
    protected $table = 'site_audit_projects';

    protected $fillable = [
        'user_id',
        'team_id',
        'domain',
        'name',
        'settings_json',
    ];

    protected $casts = [
        'settings_json' => 'array',
        'team_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(SeoChecklistTeam::class, 'team_id');
    }

    public function crawls()
    {
        return $this->hasMany(SiteAuditCrawl::class, 'project_id');
    }

    public function setting(string $key, $default = null)
    {
        return data_get($this->settings_json, $key, $default);
    }

    public static function teamColumnReady(): bool
    {
        try {
            return Schema::hasTable('site_audit_projects')
                && Schema::hasColumn('site_audit_projects', 'team_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int, int>
     */
    public static function teamIdsForMember(int $userId): array
    {
        if ($userId < 1 || ! Schema::hasTable('seo_checklist_team_members')) {
            return [];
        }

        return SeoChecklistTeamMember::query()
            ->where('user_id', $userId)
            ->pluck('team_id')
            ->map(static function ($id) {
                return (int) $id;
            })
            ->filter(static function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values()
            ->all();
    }

    public function isOwnedBy(int $userId): bool
    {
        return (int) $this->user_id === $userId;
    }

    public function isAccessibleBy(int $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        return $this->teamMemberRoleFor($userId) !== null;
    }

    public function canManageBy(int $userId): bool
    {
        return $this->isOwnedBy($userId);
    }

    public function teamMemberRoleFor(int $userId): ?string
    {
        if ($userId < 1 || ! self::teamColumnReady() || ! (int) $this->team_id) {
            return null;
        }
        if (! Schema::hasTable('seo_checklist_team_members')) {
            return null;
        }

        $role = SeoChecklistTeamMember::query()
            ->where('team_id', (int) $this->team_id)
            ->where('user_id', $userId)
            ->value('role');

        return $role !== null ? (string) $role : null;
    }
}
