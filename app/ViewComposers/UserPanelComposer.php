<?php


namespace App\ViewComposers;

use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserPanelComposer
{
    public function compose(View $view)
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if (cabinet_skip_heavy_web()) {
                $view->with([
                    'user' => $user,
                    'name' => null,
                    'tariffs' => [],
                ]);

                return;
            }

            $tariff = $user->tariff();
            $name = ($tariff) ? $tariff->name() : null;

            $tariffs = [];
            if (isset($tariff)) {
                $tariffs = $tariff->getAsArray()['settings'];
            }

            $view->with(compact('user', 'name', 'tariffs'));
        }
    }
}
