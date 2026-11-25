<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PhraseCommerceHistory extends Model
{
    protected $table = 'phrase_commerce_histories';

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

    public function enginesLabel(): string
    {
        $engines = $this->params['engines'] ?? [];
        if (! is_array($engines) || $engines === []) {
            return '—';
        }
        $map = ['yandex' => 'Я', 'google' => 'G'];
        $out = [];
        foreach ($engines as $e) {
            $out[] = $map[$e] ?? $e;
        }

        return implode('+', $out);
    }

    public function regionsLabel(): string
    {
        $p = $this->params ?? [];
        $engines = $p['engines'] ?? [];
        if (! is_array($engines) || $engines === []) {
            $engines = [];
            if (! empty($p['yandex_lr'])) {
                $engines[] = 'yandex';
            }
            if (! empty($p['google_lr'])) {
                $engines[] = 'google';
            }
        }

        $multi = count($engines) > 1;
        $parts = [];

        foreach ($engines as $engine) {
            $engine = $engine === 'google' ? 'google' : 'yandex';
            $lr = (string) ($engine === 'google' ? ($p['google_lr'] ?? '') : ($p['yandex_lr'] ?? ''));
            if ($lr === '') {
                continue;
            }
            $name = $this->resolveRegionName($engine, $lr);
            $parts[] = $multi
                ? (($engine === 'google' ? 'G: ' : 'Я: ') . $name)
                : $name;
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    public function settingsLabel(): string
    {
        return $this->regionsLabel();
    }

    private function resolveRegionName(string $engine, string $lr): string
    {
        $found = \App\Support\CompetitorSearchRegions::find($engine, $lr);
        $name = is_array($found) ? trim((string) ($found['name'] ?? '')) : '';
        if ($name === '') {
            return $lr;
        }
        // Убираем скобки вроде «(Moscow)» и хвост с кодом региона для компактности
        $short = trim(preg_replace('/\s*\([^)]*\)/u', '', $name) ?? $name);
        $short = trim(preg_replace('/\s*\[[^\]]*\]/u', '', $short) ?? $short);

        return $short !== '' ? $short : $name;
    }
}
