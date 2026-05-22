<?php

namespace App\Http\Controllers;

use App\HttpHeader;
use App\HttpHeadersSettings;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use App\Classes\Curl\CurlFacade;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;


class PagesController extends Controller
{
    /**
     * @param Request $request
     * @param HttpHeader $header
     * @return array|false|\Illuminate\Contracts\Foundation\Application|Factory|View|mixed
     */
    public function httpHeaders(Request $request, HttpHeader $header)
    {
        $lang = $header->lang;
        $user = Auth::user();
        if($user)
            $lang = $user['lang'];

        if($request->input('http', false))
            return (new CurlFacade($request->input('url')))->httpCode();

        $response = (new CurlFacade($request->input('url')))->run();
        $id = $header->saveData($response);

        return view('pages.headers', compact('response', 'id', 'lang'));
    }

    public function httpHeadersSettings(Request $request)
    {
        $settings = new HttpHeadersSettings();

        if($request->has('delete_records')){
            $settings->updateOrCreate(['code' => 'delete_records'], ['value' => $request->input('delete_records')]);

            return redirect()->route('pages.headers.settings')->with('status', __('Saved'));
        }

        $delete_records = $settings->where('code', 'delete_records')->value('value');

        return view('pages.headers-settings', compact('delete_records'));
    }

    /**
     * Keyword generator
     *
     * @return Factory|View
     */
    public function keywordGenerator()
    {
        return view('pages.keyword');
    }

    /**
     * Word duplicates
     *
     * @return Factory|View
     */
    public function duplicates()
    {
        $options = collect([
            1 => __('remove duplicate spaces between words'),
            2 => __('remove spaces and tabs at the beginning and end of the line'),
            3 => __('replace tabs with spaces'),
            4 => __('remove blank lines'),
            5 => __('convert to lowercase'),
            6 => __('remove characters at the beginning of a word'),
            7 => __('remove characters at the end of a word'),
            8 => __('remove duplicates'),
            9 => __('replace e'),
        ])->toJson();

        return view('pages.duplicates', compact('options'));
    }

    /**
     * Generator UTM Marks
     *
     * @return Factory|View
     */
    public function utmMarks()
    {
        return view('pages.utm');
    }

    /**
     * ROI Calculator
     *
     * @return Factory|View
     */
    public function roiCalculator()
    {
        return view('pages.roi', [
            'arRoi' => self::roiCalculatorMetrics(),
            'arRoiTraff' => self::roiTrafficForecastMetrics(),
        ]);
    }

    /**
     * ROI calculator metric cards (ROI tab).
     *
     * @return array<int, array<string, string>>
     */
    private static function roiCalculatorMetrics(): array
    {
        return [
            ['id_name' => 'bg-change-roi', 'id_value' => 'rez-roi-roi', 'theme' => 'danger', 'name' => 'ROI', 'text' => __('Return on investment'), 'type' => '%'],
            ['id_name' => 'bg-change-ctr', 'id_value' => 'rez-roi-ctr', 'theme' => 'danger', 'name' => 'CTR', 'text' => __('From impressions to clicks'), 'type' => '%'],
            ['id_name' => 'bg-change-ctc', 'id_value' => 'rez-roi-ctc', 'theme' => 'danger', 'name' => 'CTC', 'text' => __('From clicks to actions'), 'type' => '%'],
            ['id_name' => 'bg-change-ctb', 'id_value' => 'rez-roi-ctb', 'theme' => 'danger', 'name' => 'CTB', 'text' => __('From impressions to purchases'), 'type' => '%'],
            ['id_name' => 'bg-change-cpm', 'id_value' => 'rez-roi-cpm', 'theme' => 'warning', 'name' => 'CPM', 'text' => __('Price per 1000 impressions'), 'type' => '₽'],
            ['id_name' => 'bg-change-cpc', 'id_value' => 'rez-roi-cpc', 'theme' => 'warning', 'name' => 'CPC', 'text' => __('Price per click'), 'type' => '₽'],
            ['id_name' => 'bg-change-cpa', 'id_value' => 'rez-roi-cpa', 'theme' => 'warning', 'name' => 'CPA', 'text' => __('Price per action'), 'type' => '₽'],
            ['id_name' => 'bg-change-cps', 'id_value' => 'rez-roi-cps', 'theme' => 'warning', 'name' => 'CPS', 'text' => __('Price per sale'), 'type' => '₽'],
            ['id_name' => 'bg-change-apv', 'id_value' => 'rez-roi-apv', 'theme' => 'success', 'name' => 'APV', 'text' => __('Average check for 1 purchase'), 'type' => '₽'],
            ['id_name' => 'bg-change-apc', 'id_value' => 'rez-roi-apc', 'theme' => 'success', 'name' => 'APC', 'text' => __('Average check for 1 visit'), 'type' => '₽'],
        ];
    }

    /**
     * Traffic forecast metric cards.
     *
     * @return array<int, array<string, string>>
     */
    private static function roiTrafficForecastMetrics(): array
    {
        return [
            ['id_name' => 'bg-change-prcli', 'id_value' => 'perclicks', 'theme' => 'danger', 'name' => 'CLI', 'text' => __('Clicks'), 'type' => ' '],
            ['id_name' => 'bg-change-pract', 'id_value' => 'peractions', 'theme' => 'danger', 'name' => 'ACT', 'text' => __('Targeted actions'), 'type' => ' '],
            ['id_name' => 'bg-change-prsal', 'id_value' => 'persales', 'theme' => 'danger', 'name' => 'SAL', 'text' => __('Sales'), 'type' => ' '],
            ['id_name' => 'bg-change-prrev', 'id_value' => 'perrevenue', 'theme' => 'danger', 'name' => 'REV', 'text' => __('Income'), 'type' => '₽'],
            ['id_name' => 'bg-change-prroi', 'id_value' => 'perroi', 'theme' => 'warning', 'name' => 'ROI', 'text' => __('Return on investment'), 'type' => '%'],
        ];
    }
}
