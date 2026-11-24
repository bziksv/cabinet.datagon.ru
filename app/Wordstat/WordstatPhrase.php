<?php

namespace App\Wordstat;

use App\Services\Wordstat\WordstatPhraseKey;
use Illuminate\Database\Eloquent\Model;

class WordstatPhrase extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_MINUS = 'minus';
    public const STATUS_DELETED = 'deleted';

    public const SOURCE_LEFT = 'left';
    public const SOURCE_RIGHT = 'right';
    public const SOURCE_MANUAL = 'manual';

    protected $table = 'wordstat_phrases';
    protected $fillable = [
        'project_id', 'group_id', 'phrase', 'phrase_key', 'freq', 'freq_exact', 'source',
        'seed_phrase_id', 'depth', 'status', 'is_commercial', 'run_id',
    ];
    protected $casts = ['freq' => 'int', 'freq_exact' => 'int', 'depth' => 'int', 'is_commercial' => 'bool'];

    protected static function boot()
    {
        parent::boot();
        static::saving(function (WordstatPhrase $phrase) {
            $phrase->phrase_hash = WordstatPhraseKey::hash((string) $phrase->phrase_key);
        });
    }
}
