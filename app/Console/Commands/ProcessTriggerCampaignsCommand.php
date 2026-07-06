<?php

namespace App\Console\Commands;

use App\Classes\Cron\ProcessTriggerCampaigns;
use Illuminate\Console\Command;

class ProcessTriggerCampaignsCommand extends Command
{
    protected $signature = 'finance:process-trigger-campaigns {--dry-run : Only count audience, do not send}';

    protected $description = 'Process inactive trigger campaigns and send win-back emails';

    public function handle(): int
    {
        $result = ProcessTriggerCampaigns::run((bool) $this->option('dry-run'));

        $this->info($result['message']);
        $this->line(sprintf(
            'Campaigns: %d · Candidates: %d · Sent: %d · Skipped: %d · Failed: %d',
            $result['campaigns'],
            $result['candidates'],
            $result['sent'],
            $result['skipped'],
            $result['failed']
        ));

        return 0;
    }
}
