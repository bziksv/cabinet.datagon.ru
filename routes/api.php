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

Route::get('/domain-information/check-domain-crone', 'CroneController@checkDomains');
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
Route::post('demo/proverka-indeksacii/run', 'Api\\Demo\\IndexCheckDemoController@run');
Route::post('demo/proverka-teksta-esenin/run', 'Api\\Demo\\EseninTextCheckDemoController@run');
Route::post('demo/sbor-poiskovykh-podskazok/run', 'Api\\Demo\\SearchSuggestionsDemoController@run');
Route::post('demo/zapisi-domena/run', 'Api\\Demo\\DomainRecordsDemoController@run');
Route::post('demo/tipy-saitov-v-vydache/run', 'Api\\Demo\\SiteTypesDemoController@run');
Route::post('demo/geo-lokalizaciya-kommerciya/run', 'Api\\Demo\\PhraseCommerceDemoController@run');

Route::prefix('v1')->group(function () {
    Route::middleware(['integration.api:relevance', 'integration.api.throttle'])->group(function () {
        Route::post('relevance/analyses', 'Api\\V1\\RelevanceAnalysisController@store');
        Route::get('relevance/analyses/{id}', 'Api\\V1\\RelevanceAnalysisController@show');
        Route::get('relevance/histories', 'Api\\V1\\RelevanceAnalysisController@historiesIndex');
        Route::get('relevance/histories/{historyId}', 'Api\\V1\\RelevanceAnalysisController@history')
            ->where('historyId', '[0-9]+');
        Route::get('relevance/histories/{historyId}/missing-phrases', 'Api\\V1\\RelevanceAnalysisController@missingPhrases')
            ->where('historyId', '[0-9]+');
        Route::get('relevance/histories/{historyId}/clouds', 'Api\\V1\\RelevanceAnalysisController@clouds')
            ->where('historyId', '[0-9]+');

        Route::post('relevance/batches', 'Api\\V1\\RelevanceBatchController@store');
        Route::get('relevance/batches/{id}', 'Api\\V1\\RelevanceBatchController@show');
    });

    Route::middleware(['integration.api:ai', 'integration.api.throttle'])->group(function () {
        Route::post('ai/generate', 'Api\\V1\\AiGenerateController@store');
        Route::get('ai/generate/{recordId}', 'Api\\V1\\AiGenerateController@show')
            ->where('recordId', '[0-9]+');
    });
});
