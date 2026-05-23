<?php

namespace App\Classes\Cron;

use App\TextAnalyzerPublicShare;
use Carbon\Carbon;

class TextAnalyzerPublicSharesDelete
{
    public function __invoke()
    {
        if (!TextAnalyzerPublicShare::tableAvailable()) {
            return;
        }

        TextAnalyzerPublicShare::where('expires_at', '<', Carbon::now())->delete();

        TextAnalyzerPublicShare::whereNotNull('revoked_at')
            ->where('revoked_at', '<', Carbon::now()->subDays(7))
            ->delete();
    }
}
