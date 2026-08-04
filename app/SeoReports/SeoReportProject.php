<?php

namespace App\SeoReports;

use App\SeoChecklist\SeoChecklistTeam;
use App\SeoChecklist\SeoChecklistTeamMember;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SeoReportProject extends Model
{
    public const SHARE_ROLE_READ = 'read';
    public const SHARE_ROLE_EDIT = 'edit';

    /** Checklist team roles that can edit SEO reports. */
    public const TEAM_EDIT_ROLES = ['owner', 'pm', 'auditor'];

    /** Keys that live on the report template (shared across projects). */
    public const TEMPLATE_SETTING_KEYS = [
        'default_period',
        'default_period_month',
        'default_period_from',
        'default_period_to',
        'auto_compare',
        'compare_mode',
        'compare_month',
        'default_compare_from',
        'default_compare_to',
        'traffic_mode',
        'traffic_channels',
        'kpi_goals',
        'section_order',
        'metric_toggles',
        'auto_generate',
        'remind_missing',
        'confirmed_sources_only',
        'public_dark_theme',
        'enable_ai_summary',
    ];

    protected $table = 'seo_report_projects';

    protected $fillable = [
        'user_id',
        'team_id',
        'template_id',
        'domain',
        'title',
        'status',
        'agency_name',
        'agency_address',
        'agency_email',
        'agency_phone',
        'agency_logo_path',
        'brand_color',
        'manager_name',
        'manager_phone',
        'manager_email',
        'manager_avatar_path',
        'metrika_counter_id',
        'monitoring_project_id',
        'section_toggles',
        'settings_json',
    ];

    protected $casts = [
        'section_toggles' => 'array',
        'settings_json' => 'array',
        'metrika_counter_id' => 'integer',
        'monitoring_project_id' => 'integer',
        'template_id' => 'integer',
        'team_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistTeam::class, 'team_id');
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(SeoReportTemplate::class, 'template_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(SeoReport::class, 'project_id');
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seo_report_project_user', 'seo_report_project_id', 'user_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public static function teamColumnReady(): bool
    {
        try {
            return Schema::hasTable('seo_report_projects')
                && Schema::hasColumn('seo_report_projects', 'team_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Team IDs where the user is a member (SEO Checklist teams).
     *
     * @return array<int, int>
     */
    public static function teamIdsForMember(int $userId): array
    {
        if ($userId < 1 || !Schema::hasTable('seo_checklist_team_members')) {
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

        if ($this->teamMemberRoleFor($userId) !== null) {
            return true;
        }

        if (!Schema::hasTable('seo_report_project_user')) {
            return false;
        }

        return $this->sharedUsers()->where('users.id', $userId)->exists();
    }

    public function shareRoleFor(int $userId): ?string
    {
        if ($this->isOwnedBy($userId)) {
            return 'owner';
        }

        $teamRole = $this->teamMemberRoleFor($userId);
        if ($teamRole !== null) {
            return in_array($teamRole, self::TEAM_EDIT_ROLES, true)
                ? self::SHARE_ROLE_EDIT
                : self::SHARE_ROLE_READ;
        }

        if (!Schema::hasTable('seo_report_project_user')) {
            return null;
        }
        $row = $this->sharedUsers()->where('users.id', $userId)->first();

        return $row ? (string) ($row->pivot->role ?? self::SHARE_ROLE_READ) : null;
    }

    public function canEditBy(int $userId): bool
    {
        $role = $this->shareRoleFor($userId);

        return in_array($role, ['owner', self::SHARE_ROLE_EDIT], true);
    }

    public function teamMemberRoleFor(int $userId): ?string
    {
        if ($userId < 1 || !self::teamColumnReady() || !(int) $this->team_id) {
            return null;
        }
        if (!Schema::hasTable('seo_checklist_team_members')) {
            return null;
        }

        $role = SeoChecklistTeamMember::query()
            ->where('team_id', (int) $this->team_id)
            ->where('user_id', $userId)
            ->value('role');

        return $role !== null ? (string) $role : null;
    }

    /**
     * Live template attached to the project (null if table/column missing or unassigned).
     */
    public function resolvedTemplate(): ?SeoReportTemplate
    {
        if (!Schema::hasTable('seo_report_templates') || !Schema::hasColumn($this->getTable(), 'template_id')) {
            return null;
        }
        if (!$this->template_id) {
            return null;
        }
        if ($this->relationLoaded('reportTemplate')) {
            return $this->reportTemplate;
        }

        return $this->reportTemplate()->first();
    }

    /**
     * @return array<string, bool>
     */
    public function resolvedSectionToggles(): array
    {
        $template = $this->resolvedTemplate();
        if ($template) {
            return $template->resolvedSectionToggles();
        }

        $defaults = SeoReportSectionRegistry::defaultToggles();
        $stored = is_array($this->section_toggles) ? $this->section_toggles : [];
        foreach ($defaults as $key => $_enabled) {
            if (array_key_exists($key, $stored)) {
                $defaults[$key] = (bool) $stored[$key];
            }
        }

        return $defaults;
    }

    /**
     * Merged settings: template (report shape) + project (integrations / delivery).
     *
     * @return array<string, mixed>
     */
    public function reportSettings(): array
    {
        $projectSettings = is_array($this->settings_json) ? $this->settings_json : [];
        $template = $this->resolvedTemplate();
        $templateSettings = $template ? $template->reportSettings() : [];

        $out = $projectSettings;
        foreach (self::TEMPLATE_SETTING_KEYS as $key) {
            if (array_key_exists($key, $templateSettings)) {
                $out[$key] = $templateSettings[$key];
            }
        }

        return $out;
    }

    public function metricEnabled(string $section, string $metric): bool
    {
        return SeoReportMetricRegistry::enabled($this->reportSettings(), $section, $metric);
    }

    public function brandingAgencyName(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->agency_name : $this->agency_name;
    }

    public function brandingAgencyAddress(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->agency_address : $this->agency_address;
    }

    public function brandingAgencyEmail(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->agency_email : $this->agency_email;
    }

    public function brandingAgencyPhone(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->agency_phone : $this->agency_phone;
    }

    public function brandingColor(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->brand_color : $this->brand_color;
    }

    public function brandingManagerName(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->manager_name : $this->manager_name;
    }

    public function brandingManagerPhone(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->manager_phone : $this->manager_phone;
    }

    public function brandingManagerEmail(): ?string
    {
        $template = $this->resolvedTemplate();

        return $template ? $template->manager_email : $this->manager_email;
    }

    public function agencyLogoUrl(): ?string
    {
        $template = $this->resolvedTemplate();
        if ($template) {
            return $template->agencyLogoUrl();
        }

        return $this->publicDiskUrl($this->agency_logo_path);
    }

    public function managerAvatarUrl(): ?string
    {
        $template = $this->resolvedTemplate();
        if ($template) {
            return $template->managerAvatarUrl();
        }

        return $this->publicDiskUrl($this->manager_avatar_path);
    }

    private function publicDiskUrl(?string $path): ?string
    {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return null;
        }

        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
