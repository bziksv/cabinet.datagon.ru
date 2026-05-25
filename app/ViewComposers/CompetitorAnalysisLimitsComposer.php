<?php

namespace App\ViewComposers;

use App\Support\ModuleTariffLimit;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompetitorAnalysisLimitsComposer
{
    public const TARIFF_CODE = 'CompetitorAnalysisPhrases';

    public function compose(View $view): void
    {
        if (!Auth::check() || !$this->isCompetitorModuleRoute()) {
            $view->with('headerModuleLimit', null);
            $view->with('competitorModuleLimit', null);

            return;
        }

        $limit = ModuleTariffLimit::forUser(Auth::user(), self::TARIFF_CODE);

        $view->with('headerModuleLimit', $limit);
        $view->with('competitorModuleLimit', $limit);
    }

    protected function isCompetitorModuleRoute(): bool
    {
        $route = request()->route();

        return $route !== null && in_array($route->getName(), [
            'competitor.analysis',
            'competitor.config',
        ], true);
    }
}
