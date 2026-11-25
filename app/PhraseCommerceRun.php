<?php

namespace App;

use App\Support\CompetitorSearchRegions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhraseCommerceRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected $table = 'phrase_commerce_runs';

    protected $fillable = [
        'user_id',
        'status',
        'save_to_history',
        'history_id',
        'title',
        'params',
        'results',
        'phrases_total',
        'phrases_done',
        'current_phrase',
        'estimated_cost',
        'actual_cost',
        'requests_count',
        'errors_count',
        'error_message',
        'started_at',
        'finished_at',
        'notified_at',
    ];

    protected $casts = [
        'save_to_history' => 'boolean',
        'params' => 'array',
        'results' => 'array',
        'phrases_total' => 'integer',
        'phrases_done' => 'integer',
        'estimated_cost' => 'integer',
        'actual_cost' => 'integer',
        'requests_count' => 'integer',
        'errors_count' => 'integer',
    ];

    protected $dates = [
        'started_at',
        'finished_at',
        'notified_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(PhraseCommerceHistory::class, 'history_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_RUNNING], true);
    }

    public function enginesLabel(): string
    {
        $engines = $this->params['engines'] ?? [];
        if (! is_array($engines) || $engines === []) {
            return '—';
        }
        $map = ['yandex' => 'Яндекс', 'google' => 'Google'];
        $out = [];
        foreach ($engines as $e) {
            $out[] = $map[$e] ?? $e;
        }

        return implode(' + ', $out);
    }

    public function regionsLabel(): string
    {
        $p = $this->params ?? [];
        $engines = $p['engines'] ?? [];
        if (! is_array($engines) || $engines === []) {
            return '—';
        }
        $multi = count($engines) > 1;
        $parts = [];
        foreach ($engines as $engine) {
            $engine = $engine === 'google' ? 'google' : 'yandex';
            $lr = (string) ($engine === 'google' ? ($p['google_lr'] ?? '') : ($p['yandex_lr'] ?? ''));
            if ($lr === '') {
                continue;
            }
            $found = CompetitorSearchRegions::find($engine, $lr);
            $name = is_array($found) ? trim((string) ($found['name'] ?? '')) : '';
            if ($name !== '') {
                $short = trim(preg_replace('/\s*\([^)]*\)/u', '', $name) ?? $name);
                $short = trim(preg_replace('/\s*\[[^\]]*\]/u', '', $short) ?? $short);
                $name = $short !== '' ? $short : $name;
            } else {
                $name = $lr;
            }
            $parts[] = $multi ? (($engine === 'google' ? 'G: ' : 'Я: ') . $name) : $name;
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }
}
