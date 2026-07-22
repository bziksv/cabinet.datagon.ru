<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditFindingNote extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_FIXED = 'fixed';

    protected $table = 'site_audit_finding_notes';

    protected $fillable = [
        'project_id',
        'user_id',
        'code',
        'url_hash',
        'url',
        'status',
        'comment',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    public function isFixed(): bool
    {
        return $this->status === self::STATUS_FIXED;
    }
}
