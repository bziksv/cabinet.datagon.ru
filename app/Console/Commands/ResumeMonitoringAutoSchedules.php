<?php

namespace App\Console\Commands;

use App\Support\MonitoringPositionsSchedule;
use Illuminate\Console\Command;

class ResumeMonitoringAutoSchedules extends Command
{
    protected $signature = 'monitoring:resume-auto-schedules
                            {--project= : Только monitoring_projects.id}
                            {--user= : Владелец monitoring_projects.creator (users.id)}
                            {--email= : Email владельца (альтернатива --user)}
                            {--only-with-schedule : Только регионы с сохранённым расписанием (время/дни)}
                            {--dry-run : Показать число без UPDATE}';

    protected $description = 'Включить auto_update у регионов мониторинга (кроме владельцев Free)';

    public function handle(): int
    {
        $projectId = $this->option('project');
        $projectId = $projectId !== null && $projectId !== '' ? (int) $projectId : null;
        $onlyWithSchedule = (bool) $this->option('only-with-schedule');

        $userId = $this->option('user');
        $userId = $userId !== null && $userId !== '' ? (int) $userId : null;
        $email = trim((string) $this->option('email'));
        if ($email !== '') {
            $resolved = \App\User::query()->where('email', $email)->value('id');
            if (!$resolved) {
                $this->error("User not found: {$email}");

                return 1;
            }
            $userId = (int) $resolved;
        }

        if ($this->option('dry-run')) {
            if ($userId) {
                $count = MonitoringPositionsSchedule::countResumableAutoSchedulesForUser($userId, $onlyWithSchedule);
                $this->info("Would resume auto_update for {$count} region(s) (user {$userId}).");

                return 0;
            }
            $count = MonitoringPositionsSchedule::countResumableAutoSchedules($projectId);
            $this->info("Would resume auto_update for {$count} region(s).");

            return 0;
        }

        if ($userId) {
            $count = MonitoringPositionsSchedule::resumeAutoSchedulesForUser($userId, $onlyWithSchedule);
            $this->info("Resumed auto_update for {$count} region(s) (user {$userId}).");

            return 0;
        }

        $count = MonitoringPositionsSchedule::resumePaidAutoSchedules($projectId);
        $suffix = $projectId ? " (project {$projectId})" : '';
        $this->info("Resumed auto_update for {$count} region(s){$suffix}.");

        return 0;
    }
}
