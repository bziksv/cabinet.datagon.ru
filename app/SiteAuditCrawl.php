<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteAuditCrawl extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_DISCOVERING = 'discovering';
    public const STATUS_FETCHING = 'fetching';
    public const STATUS_AGGREGATING = 'aggregating';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_QUEUED_WAIT = 'queued_wait';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'site_audit_crawls';

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'pages_total',
        'pages_fetched',
        'pages_limit',
        'buckets_json',
        'counts_json',
        'progress_json',
        'error',
        'save_html',
        'share_token',
        'share_enabled_at',
        'share_white_label',
        'share_brand_name',
        'share_brand_url',
        'share_brand_logo',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'buckets_json' => 'array',
        'counts_json' => 'array',
        'progress_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'share_enabled_at' => 'datetime',
        'share_white_label' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    public function pages()
    {
        return $this->hasMany(SiteAuditPage::class, 'crawl_id');
    }

    public function findings()
    {
        return $this->hasMany(SiteAuditFinding::class, 'crawl_id');
    }

    public function stats()
    {
        return $this->hasMany(SiteAuditCrawlStat::class, 'crawl_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_DONE,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isActive(): bool
    {
        return ! $this->isFinished();
    }

    public static function statusLabel(?string $status): string
    {
        $map = [
            self::STATUS_QUEUED => 'Запуск',
            self::STATUS_DISCOVERING => 'Сбор URL',
            self::STATUS_FETCHING => 'Сканирование',
            self::STATUS_AGGREGATING => 'Агрегация',
            self::STATUS_DONE => 'Готово',
            self::STATUS_FAILED => 'Ошибка',
            self::STATUS_QUEUED_WAIT => 'Ждёт слот',
            self::STATUS_CANCELLED => 'Остановлен',
        ];

        return $map[$status] ?? (string) $status;
    }

    /** Полная подпись статуса для title/tooltip. */
    public static function statusLabelFull(?string $status): string
    {
        if ($status === self::STATUS_QUEUED_WAIT) {
            return 'В очереди — ждёт свободный слот (лимит одновременных проверок)';
        }

        return self::statusLabel($status);
    }

    public function statusLabelRu(): string
    {
        if ($this->status === self::STATUS_AGGREGATING) {
            $detail = $this->aggregateStageLabel();
            if ($detail !== null) {
                return $detail;
            }
        }

        return self::statusLabel($this->status);
    }

    public function statusLabelFullRu(): string
    {
        if ($this->status === self::STATUS_AGGREGATING) {
            $detail = $this->aggregateStageLabel();
            if ($detail !== null) {
                return 'Агрегация: ' . $detail . ' (финальный этап после сканирования)';
            }
        }

        return self::statusLabelFull($this->status);
    }

    /**
     * Короткая подпись текущего этапа агрегации (чтобы не казалось, что «Агрегация» зависла).
     */
    public function aggregateStageLabel(): ?string
    {
        $progress = is_array($this->progress_json) ? $this->progress_json : [];
        $stage = (string) (($progress['aggregate']['stage'] ?? '') ?: '');
        if ($stage === '' || $stage === 'done') {
            return null;
        }

        if ($stage === 'psi') {
            $psi = is_array($progress['psi'] ?? null) ? $progress['psi'] : [];
            if (! empty($psi['skipped'])) {
                return 'PSI пропуск';
            }
            $cursor = (int) ($psi['cursor'] ?? 0);
            $sampled = (int) ($psi['sampled'] ?? 0);
            if ($sampled > 0) {
                return 'PSI ' . min($cursor, $sampled) . '/' . $sampled;
            }

            return 'PageSpeed';
        }

        $map = [
            'serp_snippets' => 'Сниппеты',
            'serp_index' => 'Индекс',
            'serp_cannibalization' => 'Каннибал.',
            'availability' => 'Доступность',
            'cannibalization' => 'Каннибал.',
            'finalize' => 'Финиш',
            'click_depth' => 'Глубина',
            'sitemap_coverage' => 'Sitemap',
            'landing_coverage' => 'Посадочные',
            'broken_links' => 'Битые',
            'from_pages' => 'Страницы',
        ];

        return $map[$stage] ?? ('Этап ' . $stage);
    }

    public function statusCssClass(): string
    {
        if ($this->status === self::STATUS_DONE) {
            return 'done';
        }
        if ($this->status === self::STATUS_FAILED || $this->status === self::STATUS_CANCELLED) {
            return 'failed';
        }

        return 'run';
    }

    /**
     * Оценка окончания по текущей скорости (started_at → pages_fetched).
     * null если ещё рано считать или проверка завершён.
     */
    public function estimateFinishedAt(): ?\Carbon\Carbon
    {
        if ($this->isFinished() || $this->finished_at) {
            return null;
        }

        $fetched = (int) $this->pages_fetched;
        $total = (int) $this->pages_total;
        if ($fetched < 15 || $total <= $fetched) {
            return null;
        }

        $start = $this->started_at ?: $this->created_at;
        if (! $start) {
            return null;
        }

        $elapsed = max(1, now()->getTimestamp() - $start->getTimestamp());
        $rate = $fetched / $elapsed;
        if ($rate < 0.01) {
            return null;
        }

        $secondsLeft = (int) ceil(($total - $fetched) / $rate);
        // защита от абсурда (больше ~14 суток)
        if ($secondsLeft > 14 * 24 * 3600) {
            return null;
        }

        return now()->addSeconds($secondsLeft);
    }

    public function estimateFinishedAtFormatted(): ?string
    {
        $at = $this->estimateFinishedAt();

        return $at ? $at->format('d.m H:i') : null;
    }

    public function isShared(): bool
    {
        return $this->share_token && $this->share_enabled_at;
    }

    public function publicShareUrl(): ?string
    {
        if (! $this->isShared()) {
            return null;
        }

        return route('site-audit.public.share.view', $this->share_token);
    }

    public function isWhiteLabelShare(): bool
    {
        return (bool) $this->share_white_label;
    }

    /**
     * @return array{enabled:bool,brand_name:?string,brand_url:?string,brand_logo_url:?string}
     */
    public function whiteLabelMeta(): array
    {
        $name = is_string($this->share_brand_name) ? trim($this->share_brand_name) : '';
        $url = is_string($this->share_brand_url) ? trim($this->share_brand_url) : '';
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $logoUrl = null;
        $logo = is_string($this->share_brand_logo) ? trim($this->share_brand_logo) : '';
        if ($logo !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            $logoUrl = asset('storage/' . ltrim($logo, '/'));
        }

        return [
            'enabled' => $this->isWhiteLabelShare(),
            'brand_name' => $name !== '' ? mb_substr($name, 0, 120) : null,
            'brand_url' => $url !== '' ? mb_substr($url, 0, 255) : null,
            'brand_logo_url' => $logoUrl,
        ];
    }

    public function clearWhiteLabelLogo(): void
    {
        $logo = is_string($this->share_brand_logo) ? trim($this->share_brand_logo) : '';
        if ($logo !== '') {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($logo);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $this->share_brand_logo = null;
    }

    /**
     * Битый UTF-8 (часто из monitoring.query) ломал aggregate → проверка зависал в «Агрегация».
     */
    public function setProgressJsonAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['progress_json'] = null;

            return;
        }
        if (! is_array($value)) {
            $value = (array) $value;
        }
        $value = \App\Services\SiteAudit\SiteAuditUtf8::scrub($value);
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $this->attributes['progress_json'] = json_encode($value, $flags);
    }
}
