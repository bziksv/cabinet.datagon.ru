<?php

namespace App\SeoChecklist;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class SeoChecklistTeam extends Model
{
    protected $table = 'seo_checklist_teams';

    protected $fillable = [
        'user_id', 'title', 'description',
    ];

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('seo_checklist_teams');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function members(): HasMany
    {
        return $this->hasMany(SeoChecklistTeamMember::class, 'team_id')
            ->orderBy('role')
            ->orderBy('id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(SeoChecklistProject::class, 'team_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabels(): array
    {
        return [
            'owner' => __('SEO role owner'),
            'auditor' => __('SEO role auditor'),
            'pm' => __('SEO role PM'),
            'participant' => __('SEO role participant'),
        ];
    }

    /**
     * @return string[]
     */
    public static function roleKeys(): array
    {
        return ['owner', 'auditor', 'pm', 'participant'];
    }
}
