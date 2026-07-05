<?php

namespace App\Console\Commands;

use App\Support\MonitoringPositionsSchedule;
use Illuminate\Console\Command;

class SuspendMonitoringAutoSchedules extends Command
{
    protected $signature = 'monitoring:suspend-auto-schedules
                            {--free-only : Только пользователи с ролью Free}';

    protected $description = 'Приостановить auto_update у регионов мониторинга позиций (расписание сохраняется)';

    public function handle(): int
    {
        if ($this->option('free-only')) {
            $count = MonitoringPositionsSchedule::suspendAutoSchedulesForFreeUsers();
            $this->info("Suspended auto_update for {$count} region(s) (Free tariff only).");
        } else {
            $count = MonitoringPositionsSchedule::suspendAllAutoSchedules();
            $this->info("Suspended auto_update for {$count} region(s) (all users).");
        }

        return 0;
    }
}
