<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TextUniquenessHistory extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'params',
        'results',
        'mode',
        'uniqueness_pct',
        'cost',
    ];

    protected $casts = [
        'params' => 'array',
        'results' => 'array',
    ];

    public function enginesLabel(): string
    {
        $engine = $this->params['engine'] ?? 'yandex';

        return $engine === 'google' ? 'G' : 'Я';
    }

    public function modeLabel(): string
    {
        return ($this->mode ?? '') === 'urls' ? 'URL' : 'Интернет';
    }
}
