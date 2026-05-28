<?php

use App\Classes\Locations\Searches\Yandex;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/backlink/scan-links', 'CroneController@scanLinks');
Route::get('/backlink/scan-broken-links', 'CroneController@scanBrokenLinks');
Route::get('/domain-monitoring/check-link-crone/{timing}', 'CroneController@checkLinkCrone');

Route::get('/domain-information/check-domain-crone/', 'CroneController@checkDomains');

Route::get('location', 'Api\LocationSearchController@index');

Route::get('yandex-location-update', function(){

    set_time_limit(300);

    $file = 'yandex.txt';
    $path = storage_path('location');

    $city = $path .'/'. $file;
    $arrCity = [];

    $fp = fopen($city, "r");
    if($fp){
        while (($buffer = fgets($fp)) !== false)
            $arrCity[] = trim($buffer);

        fclose($fp);

        $location = new Yandex();
        foreach ($arrCity as $city)
            $location->get($city);
    }
});

Route::get('checkYandexToken/{name?}', function($name = "Воронеж"){
    $location = new Yandex();
    dd($location->requestYandex($name));
});

Route::post('bot', 'TelegramBotController@index');

Route::post('demo/analiz-teksta/run', 'Api\\Demo\\TextAnalyzerDemoController@run');
Route::post('demo/analiz-konkurentov/run', 'Api\\Demo\\CompetitorAnalysisDemoController@run');
Route::post('demo/vydelenie-unikalnykh-slov-v-tekste/run', 'Api\\Demo\\UniqueWordsDemoController@run');
Route::post('demo/klasterizator-klyuchevykh-slov/run', 'Api\\Demo\\ClusterDemoController@run');
Route::post('demo/klasterizator-klyuchevykh-slov/poll', 'Api\\Demo\\ClusterDemoController@poll');
Route::post('demo/monitoring-saytov/run', 'Api\\Demo\\SiteMonitoringDemoController@run');
Route::post('demo/proverka-meta-tegov-online/run', 'Api\\Demo\\MetaTagsDemoController@run');
Route::post('demo/otslezhivanie-sroka-registratsii-domenov/run', 'Api\\Demo\\DomainInformationDemoController@run');
Route::post('demo/otslezhivanie-ssylok/run', 'Api\\Demo\\BacklinkDemoController@run');
Route::post('demo/http-headers/run', 'Api\\Demo\\HttpHeadersDemoController@run');
