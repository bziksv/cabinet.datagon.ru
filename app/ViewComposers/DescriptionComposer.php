<?php


namespace App\ViewComposers;

use App\Description;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
class DescriptionComposer
{
    /** UI v2 — те же уроки/описания, что у классического модуля. */
    private const CODE_ALIASES = [
        'monitoring-v2' => 'monitoring',
        'cluster-v2' => 'cluster',
    ];

    public function compose(View $view)
    {
        $path = request()->path();
        $code = self::CODE_ALIASES[$path] ?? $path;

        $description = Description::where(['code' => $code, 'lang' => App::getLocale()])->get();

        $description = $description->filter(function ($value) {
            return (!is_null($value->description));
        });

        $description = $description->keyBy('position');

        $view->with(compact('code', 'description'));
    }
}
