<?php

namespace App\ViewComposers;

use App\News;
use App\NewsNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CountUnreadNewsComposer
{
    public function compose(View $view)
    {
        if (! Auth::check()) {
            $view->with('count', 0);

            return;
        }

        if (cabinet_skip_heavy_web()) {
            $view->with('count', 0);

            return;
        }

        $notification = NewsNotification::where('user_id', Auth::id())->first();
        if ($notification !== null) {
            $count = News::where('created_at', '>=', $notification->last_check)->count();
        } else {
            $count = News::count();
        }

        $view->with(compact('count'));
    }
}
