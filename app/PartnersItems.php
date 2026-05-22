<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartnersItems extends Model
{
    protected $table = 'partners_items';

    protected $guarded = [];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnersGroups::class, 'partners_groups_id', 'id');
    }

    public function delete()
    {
        $localPath = public_path('storage/' . ltrim(str_replace('\\', '/', (string) $this->image), '/'));
        if (file_exists($localPath)) {
            unlink($localPath);
        }

        parent::delete();
    }

    public function generateShortLink($lang): string
    {
        $link = Str::random();

        if (empty($this->where('short_link_' . $lang, '=', $link)->first())) {
            return $link;
        }

        return $this->generateShortLink($lang);
    }
}
