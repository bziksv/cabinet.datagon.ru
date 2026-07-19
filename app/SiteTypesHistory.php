<?php

namespace App;

use App\Support\CompetitorSearchRegions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteTypesHistory extends Model
{
    protected $table = 'site_types_histories';

    protected $fillable = [
        'user_id',
        'title',
        'params',
        'results',
        'phrases_count',
        'results_count',
        'cost',
    ];

    protected $casts = [
        'params' => 'array',
        'results' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enginesLabel(): string
    {
        $engines = $this->params['engines'] ?? [];
        if (! is_array($engines) || $engines === []) {
            return '—';
        }

        $labels = [];
        foreach ($engines as $engine) {
            if ($engine === 'yandex') {
                $labels[] = __('Yandex');
            } elseif ($engine === 'google') {
                $labels[] = __('Google');
            } else {
                $labels[] = (string) $engine;
            }
        }

        return implode(', ', $labels);
    }

    public function settingsLabel(): string
    {
        $params = is_array($this->params) ? $this->params : [];
        $parts = [];

        $depth = (int) ($params['depth'] ?? 10);
        $parts[] = 'топ-' . $depth;

        $engines = $params['engines'] ?? [];
        if (is_array($engines) && in_array('yandex', $engines, true)) {
            $lr = (string) ($params['yandex_lr'] ?? '');
            $region = $lr !== '' ? CompetitorSearchRegions::find('yandex', $lr) : null;
            if ($region) {
                $parts[] = 'Я: ' . ($region['name'] ?? $lr);
            } elseif ($lr !== '') {
                $parts[] = 'Я lr ' . $lr;
            }
        }
        if (is_array($engines) && in_array('google', $engines, true)) {
            $lr = (string) ($params['google_lr'] ?? '');
            $region = $lr !== '' ? CompetitorSearchRegions::find('google', $lr) : null;
            if ($region) {
                $parts[] = 'G: ' . ($region['name'] ?? $lr);
            } elseif ($lr !== '') {
                $parts[] = 'G ' . $lr;
            }
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }
}
