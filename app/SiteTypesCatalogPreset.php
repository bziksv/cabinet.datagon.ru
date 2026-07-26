<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property array $domains
 */
class SiteTypesCatalogPreset extends Model
{
    protected $table = 'site_types_catalog_presets';

    protected $guarded = [];

    protected $casts = [
        'domains' => 'array',
    ];
}
