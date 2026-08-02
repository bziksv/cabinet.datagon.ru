<?php

namespace App\SeoReports;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SeoReport extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_APPROVED = 'approved_by_client';

    protected $table = 'seo_reports';

    protected $fillable = [
        'project_id',
        'user_id',
        'archived_from_report_id',
        'status',
        'period_from',
        'period_to',
        'compare_from',
        'compare_to',
        'public_token',
        'public_pin',
        'snapshot_json',
        'section_states',
        'comments_json',
        'summary_text',
        'work_done_text',
        'work_plan_text',
        'fail_reason',
        'generated_at',
        'approved_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'compare_from' => 'date',
        'compare_to' => 'date',
        'snapshot_json' => 'array',
        'section_states' => 'array',
        'comments_json' => 'array',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
        'archived_from_report_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoReportProject::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ensurePublicToken(): string
    {
        if (!empty($this->public_token)) {
            return (string) $this->public_token;
        }

        $this->public_token = Str::random(40);
        $this->save();

        return (string) $this->public_token;
    }

    public function statusLabel(): string
    {
        $map = [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_GENERATING => 'Генерируется',
            self::STATUS_READY => 'Готов',
            self::STATUS_FAILED => 'Ошибка',
            self::STATUS_APPROVED => 'Утверждён клиентом',
        ];

        return $map[$this->status] ?? (string) $this->status;
    }
}
