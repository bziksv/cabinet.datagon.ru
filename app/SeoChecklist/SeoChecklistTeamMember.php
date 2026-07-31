<?php

namespace App\SeoChecklist;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoChecklistTeamMember extends Model
{
    protected $table = 'seo_checklist_team_members';

    protected $fillable = [
        'team_id', 'user_id', 'role',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistTeam::class, 'team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
