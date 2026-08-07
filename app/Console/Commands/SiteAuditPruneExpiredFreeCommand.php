<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditPruner;
use App\Support\SiteAuditLimits;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteAuditPruneExpiredFreeCommand extends Command
{
    protected $signature = 'site-audit:prune-expired-free
                            {--dry-run : Только показать, кого затронет}';

    protected $description = 'Удаляет историю аудита у пользователей, ушедших с платного на Free более N дней назад';

    public function handle(): int
    {
        if (! Schema::hasTable('site_audit_user_state')) {
            $this->warn('Таблица site_audit_user_state ещё нет — сначала migrate');

            return 0;
        }

        $days = max(1, (int) config('site_audit.free_history_keep_days', 14));
        $cutoff = Carbon::now()->subDays($days);
        $dry = (bool) $this->option('dry-run');
        $pruner = new SiteAuditPruner();
        $purgedUsers = 0;
        $purgedCrawls = 0;

        // пометить новых «стал Free» среди тех, у кого есть история
        $userIdsWithAudit = DB::table('site_audit_crawls')->distinct()->pluck('user_id')
            ->merge(DB::table('site_audit_projects')->distinct()->pluck('user_id'))
            ->unique();

        foreach ($userIdsWithAudit as $uid) {
            $user = User::query()->find($uid);
            if ($user) {
                SiteAuditLimits::touchDowngradeState($user);
            }
        }

        $rows = DB::table('site_audit_user_state')
            ->whereNotNull('became_free_at')
            ->where('became_free_at', '<=', $cutoff)
            ->whereNull('history_purged_at')
            ->get();

        foreach ($rows as $row) {
            $user = User::query()->find($row->user_id);
            if (! $user) {
                continue;
            }
            // снова платный — не трогаем
            if ($user->hasPaidTariffRole()) {
                DB::table('site_audit_user_state')->where('user_id', $user->id)->delete();
                continue;
            }
            if (! $user->onFreeTariff()) {
                continue;
            }

            $this->line("user #{$user->id} {$user->email} became_free={$row->became_free_at}");
            if ($dry) {
                continue;
            }

            $n = $pruner->purgeUserHistory((int) $user->id);
            DB::table('site_audit_user_state')->where('user_id', $user->id)->update([
                'history_purged_at' => now(),
                'updated_at' => now(),
            ]);
            $purgedUsers++;
            $purgedCrawls += $n;
        }

        $this->info($dry
            ? 'Dry-run done'
            : "Purged users={$purgedUsers}, crawls={$purgedCrawls}");

        return 0;
    }
}
