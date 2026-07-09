<?php

namespace App\Classes\Cron;

use App\EseninTextCheckPublicShare;
use Carbon\Carbon;

class EseninTextCheckPublicSharesDelete
{
    public function __invoke(): void
    {
        if (!EseninTextCheckPublicShare::tableAvailable()) {
            return;
        }

        EseninTextCheckPublicShare::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }
}
