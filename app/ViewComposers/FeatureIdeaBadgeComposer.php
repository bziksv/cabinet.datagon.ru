<?php

namespace App\ViewComposers;

use App\Support\FeatureIdeaAccess;
use Illuminate\View\View;

class FeatureIdeaBadgeComposer
{
    public function compose(View $view): void
    {
        $view->with([
            'ideasModerationCount' => FeatureIdeaAccess::staffPendingCount(),
        ]);
    }
}
