<?php

namespace App\Console\Commands;

use App\Services\SeoChecklist\SeoChecklistService;
use Illuminate\Console\Command;

class SeoChecklistResetRecurring extends Command
{
    protected $signature = 'seo-checklist:reset-recurring';

    protected $description = 'Reset done recurring SEO checklist tasks and spawn next cycle when previous is stuck in review';

    public function handle(SeoChecklistService $service): int
    {
        $count = $service->resetRecurringDue();
        $this->info('Recurring SEO checklist items processed: ' . $count);

        return 0;
    }
}
