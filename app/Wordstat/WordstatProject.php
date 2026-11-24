<?php

namespace App\Wordstat;

use App\SeoChecklist\SeoChecklistTeamMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WordstatProject extends Model
{
    use SoftDeletes;

    protected $table = 'wordstat_projects';
    protected $fillable = ['user_id', 'team_id', 'domain', 'title', 'region_ids', 'device'];
    protected $casts = ['region_ids' => 'array', 'user_id' => 'int', 'team_id' => 'int'];

    public function groups()
    {
        return $this->hasMany(WordstatGroup::class, 'project_id')->orderBy('position')->orderBy('id');
    }

    public function phrases()
    {
        return $this->hasMany(WordstatPhrase::class, 'project_id');
    }

    public function runs()
    {
        return $this->hasMany(WordstatRun::class, 'project_id')->orderByDesc('id');
    }

    /** @return array<int, int> */
    public static function teamIdsForMember(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        return SeoChecklistTeamMember::query()->where('user_id', $userId)
            ->pluck('team_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }

    public function scopeAccessibleBy($query, int $userId)
    {
        $teamIds = self::teamIdsForMember($userId);

        return $query->where(function ($q) use ($userId, $teamIds) {
            $q->where('user_id', $userId);
            if ($teamIds !== []) {
                $q->orWhereIn('team_id', $teamIds);
            }
        });
    }

    public function isAccessibleBy(int $userId): bool
    {
        if ((int) $this->user_id === $userId) {
            return true;
        }

        return $this->team_id && in_array((int) $this->team_id, self::teamIdsForMember($userId), true);
    }
}
