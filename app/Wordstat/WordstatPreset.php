<?php

namespace App\Wordstat;

use Illuminate\Database\Eloquent\Model;

class WordstatPreset extends Model
{
    protected $table = 'wordstat_presets';
    protected $fillable = ['user_id', 'team_id', 'title', 'settings'];
    protected $casts = ['settings' => 'array', 'user_id' => 'int', 'team_id' => 'int'];

    public function scopeVisibleTo($query, int $userId)
    {
        $teamIds = WordstatProject::teamIdsForMember($userId);

        return $query->where(function ($q) use ($userId, $teamIds) {
            $q->where('user_id', $userId);
            if ($teamIds !== []) {
                $q->orWhereIn('team_id', $teamIds);
            }
        });
    }
}
