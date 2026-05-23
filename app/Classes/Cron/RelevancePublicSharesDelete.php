<?php

namespace App\Classes\Cron;

use App\RelevancePublicShare;
use Carbon\Carbon;

class RelevancePublicSharesDelete
{
    public function __invoke()
    {
        RelevancePublicShare::where('expires_at', '<', Carbon::now())->delete();

        RelevancePublicShare::whereNotNull('revoked_at')
            ->where('revoked_at', '<', Carbon::now()->subDays(7))
            ->delete();
    }
}
