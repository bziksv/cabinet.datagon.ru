<?php

namespace App;

use App\Support\YandexLrRegions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchSuggestionsHistory extends Model
{
    protected $table = 'search_suggestions_histories';

    protected $fillable = [
        'user_id',
        'title',
        'params',
        'results',
        'seeds_count',
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

        $modeLabels = [
            'phrase' => 'фраза',
            'space' => '+пробел',
            'en' => 'a-z',
            'ru' => 'а-я',
            'digits' => '0-9',
        ];
        foreach ($modeLabels as $key => $label) {
            if (! empty($params['modes'][$key])) {
                $parts[] = $label;
            }
        }

        $presetLabels = [
            'local' => 'Local',
            'shopping' => 'Шопинг',
            'questions' => 'Вопросы',
            'reviews' => 'Отзывы',
        ];
        foreach ($presetLabels as $key => $label) {
            if (! empty($params['presets'][$key])) {
                $parts[] = $label;
            }
        }

        $depth = max(1, (int) ($params['depth'] ?? 1));
        $parts[] = 'глубина ' . $depth;

        $engines = $params['engines'] ?? [];
        if (is_array($engines) && in_array('yandex', $engines, true)) {
            $lr = (string) ($params['yandex_lr'] ?? '');
            $region = $lr !== '' ? YandexLrRegions::find($lr) : null;
            if ($region) {
                $parts[] = $region['name'];
            } elseif ($lr !== '') {
                $parts[] = 'lr ' . $lr;
            }
        }
        if (is_array($engines) && in_array('google', $engines, true)) {
            $domain = (string) ($params['google_domain'] ?? '');
            $gl = strtolower((string) ($params['google_gl'] ?? ''));
            $countries = config('cabinet-search-suggestions.google_countries', []);
            if ($domain !== '') {
                $parts[] = $domain;
            }
            if ($gl !== '' && isset($countries[$gl]['name'])) {
                $parts[] = $countries[$gl]['name'];
            } elseif ($gl !== '') {
                $parts[] = strtoupper($gl);
            }
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }
}
