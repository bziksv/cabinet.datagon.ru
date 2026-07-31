<?php

namespace App\Console\Commands;

use App\Services\SeoChecklist\SeoChecklistService;
use Illuminate\Console\Command;

class SeoChecklistResetRecurring extends Command
{
    protected $signature = 'seo-checklist:reset-recurring';

    protected $description = 'Reset done SEO checklist tasks with monthly/weekly repeat_rule';

    public function handle(SeoChecklistService $service): int
    {
        $count = $service->resetRecurringDue();
        $this->info('Reset recurring SEO checklist items: ' . $count);

        return 0;
    }
}
