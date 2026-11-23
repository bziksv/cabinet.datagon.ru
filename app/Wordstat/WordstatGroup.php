<?php

namespace App\Wordstat;

use Illuminate\Database\Eloquent\Model;

class WordstatGroup extends Model
{
    public const DEFAULT_NAME = 'Без группы';

    protected $table = 'wordstat_groups';
    protected $fillable = ['project_id', 'name', 'page_url', 'source', 'position'];

    public function phrases()
    {
        return $this->hasMany(WordstatPhrase::class, 'group_id');
    }
}
