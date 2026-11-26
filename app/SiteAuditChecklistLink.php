<?php

namespace App;

use App\SeoChecklist\SeoChecklistProject;
use App\Support\SchemaMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteAuditChecklistLink extends Model
{
    protected $table = 'site_audit_checklist_links';

    public const DEFAULT_SEVERITIES = ['critical', 'important'];

    public const ALLOWED_SEVERITIES = ['critical', 'important', 'warning', 'other'];

    protected $fillable = [
        'site_audit_project_id',
        'checklist_project_id',
        'enabled',
        'severities_json',
        'stage_mode',
        'fallback_stage_key',
        'default_role',
        'due_days_critical',
        'due_days_important',
        'due_days_other',
        'last_synced_crawl_id',
        'last_synced_at',
        'last_sync_summary_json',
        'created_by',
    ];

    protected $casts = [
        'site_audit_project_id' => 'integer',
        'checklist_project_id' => 'integer',
        'enabled' => 'boolean',
        'severities_json' => 'array',
        'due_days_critical' => 'integer',
        'due_days_important' => 'integer',
        'due_days_other' => 'integer',
        'last_synced_crawl_id' => 'integer',
        'last_synced_at' => 'datetime',
        'last_sync_summary_json' => 'array',
        'created_by' => 'integer',
    ];

    public static function tableReady(): bool
    {
        return SchemaMemo::hasTable('site_audit_checklist_links');
    }

    public function siteAuditProject(): BelongsTo
    {
        return $this->belongsTo(SiteAuditProject::class, 'site_audit_project_id');
    }

    public function checklistProject(): BelongsTo
    {
        return $this->belongsTo(SeoChecklistProject::class, 'checklist_project_id');
    }

    /**
     * @return array<int, string>
     */
    public function severities(): array
    {
        $raw = is_array($this->severities_json) ? $this->severities_json : self::DEFAULT_SEVERITIES;
        $filtered = array_values(array_intersect(self::ALLOWED_SEVERITIES, array_map('strval', $raw)));

        return $filtered !== [] ? $filtered : self::DEFAULT_SEVERITIES;
    }

    public function dueDaysForSeverity(string $severity): int
    {
        if ($severity === 'critical') {
            return max(0, (int) ($this->due_days_critical ?? 3));
        }
        if ($severity === 'important') {
            return max(0, (int) ($this->due_days_important ?? 7));
        }

        return max(0, (int) ($this->due_days_other ?? 14));
    }
}
