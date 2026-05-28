<?php


namespace App\Classes\Locations\Searches;


use App\Classes\Locations\Region;
use App\Support\GoogleGeoRegions;

class Google extends Region
{
    public function __construct()
    {
        $this->source = 'google';

        parent::__construct();
    }

    public function get(string $name)
    {
        $jsonHits = GoogleGeoRegions::search($name, 25);
        if ($jsonHits !== []) {
            return collect(array_map(function (array $item) {
                $label = (string) $item['name'];
                if (!empty($item['name_en']) && $item['name_en'] !== $label) {
                    $label = $label . ' (' . $item['name_en'] . ')';
                }

                return $this->store((string) $item['id'], $label);
            }, $jsonHits));
        }

        $needle = mb_strtolower($name);
        $location = $this->location->where('source', $this->source)
            ->where(function ($query) use ($needle, $name) {
                $query->where('name', 'like', $name . '%')
                    ->orWhere('name', 'like', '%' . $name . '%')
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle . '%']);
            });

        if ($location->count()) {
            return $location->limit(25)->get();
        }

        return false;
    }
}
