<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditPruner;
use Illuminate\Console\Command;

class SiteAuditPruneCommand extends Command
{
    protected $signature = 'site-audit:prune
                            {--user= : Только краулы пользователя (лимит тарифа SiteAuditProjects)}
                            {--project= : Только один project_id (history_keep_per_project)}
                            {--keep= : Сколько последних краулов оставить (default: тариф или config)}';

    protected $description = 'Удаляет старые site audit краулы сверх лимита хранения';

    public function handle(): int
    {
        $pruner = new SiteAuditPruner();
        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;

        if ($this->option('project')) {
            $n = $pruner->pruneProject((int) $this->option('project'), $keep);
            $this->info("Deleted {$n} crawl(s) for project " . $this->option('project'));

            return 0;
        }

        if ($this->option('user') !== null) {
            $userId = (int) $this->option('user');
            $n = $pruner->pruneUserToLimit($userId, $keep);
            $this->info("Deleted {$n} crawl(s) for user {$userId} (storage cap)");

            return 0;
        }

        // Все пользователи: лимит тарифа на каждого
        $userIds = \App\SiteAuditCrawl::query()->select('user_id')->distinct()->pluck('user_id');
        $total = 0;
        foreach ($userIds as $uid) {
            $total += $pruner->pruneUserToLimit((int) $uid, $keep);
        }
        $this->info("Deleted {$total} crawl(s)");

        return 0;
    }
}
