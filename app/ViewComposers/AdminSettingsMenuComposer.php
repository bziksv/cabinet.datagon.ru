<?php

namespace App\ViewComposers;

use App\Support\CabinetAdminMenu;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminSettingsMenuComposer
{
    public function compose(View $view): void
    {
        if (Auth::check()) {
            apply_global_team_permissions();
            Auth::user()->loadMissing('roles');
        }

        $view->with('adminMenuItems', CabinetAdminMenu::items());
    }
}
