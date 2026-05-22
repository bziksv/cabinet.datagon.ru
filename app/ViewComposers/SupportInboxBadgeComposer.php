<?php

namespace App\ViewComposers;

use App\Support\SupportAccess;
use App\SupportTicket;
use Illuminate\View\View;

class SupportInboxBadgeComposer
{
    public function compose(View $view): void
    {
        $count = SupportAccess::headerBadgeCount();
        $filter = null;
        $badgeTitle = '';

        if ($count > 0) {
            if (SupportAccess::isStaff()) {
                $filter = SupportTicket::STATUS_OPEN;
                $badgeTitle = __('Needs reply');
            } else {
                $filter = SupportTicket::STATUS_ANSWERED;
                $badgeTitle = __('New reply from support');
            }
        }

        $view->with([
            'supportBadgeCount' => $count,
            'supportBadgeFilter' => $filter,
            'supportBadgeTitle' => $badgeTitle,
        ]);
    }
}
