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

    public function settingsLabel(): string
    {
        $p = $this->params ?? [];
        $y = $p['yandex_lr'] ?? '';
        $g = $p['google_lr'] ?? '';
        $parts = [];
        if ($y !== '') {
            $parts[] = 'Я:' . $y;
        }
        if ($g !== '') {
            $parts[] = 'G:' . $g;
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }
}
