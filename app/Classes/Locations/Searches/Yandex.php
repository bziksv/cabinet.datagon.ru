<?php

namespace App\Classes\Locations\Searches;

use App\Classes\Locations\Region;
use App\Support\YandexLrRegions;
use Ixudra\Curl\Facades\Curl;

class Yandex extends Region
{
    private $url;
    private $token;
    private $client_id;

    public function __construct()
    {
        $this->source = 'yandex';
        $config = config('location.yandex');

        $this->url = $config['url'];
        $this->token = $config['token'];
        $this->client_id = $config['client_id'];

        parent::__construct();
    }

    public function get(string $name)
    {
        $jsonHits = YandexLrRegions::search($name, 25);
        if ($jsonHits !== []) {
            return collect(array_map(function (array $item) {
                return $this->store((string) $item['id'], (string) $item['name']);
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

        $response = $this->requestYandex($name);

        if(!$response->regions)
            return false;

        $regions = collect();
        foreach ($response->regions as $region){

            $lr = $region->id;
            $name = $region->name . ', ' . $region->parent->name;

            $location = $this->store($lr, $name);
            $regions->push($location);
        }

        return $regions;
    }

    public function requestYandex(string $name)
    {
        $response = Curl::to($this->url)
            ->withHeader('Authorization: OAuth oauth_token="'. $this->token .'", oauth_client_id="'. $this->client_id .'"')
            ->withData(['name' => $name])
            ->asJson()
            ->get();

        return $response;
    }


}
