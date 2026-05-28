<?php

namespace App\Listeners;

use App\Classes\Monitoring\ProjectFaviconService;
use App\Events\MonitoringProjectCreated;

class RefreshMonitoringProjectFavicon
{
    public function handle(MonitoringProjectCreated $event): void
    {
        try {
            app(ProjectFaviconService::class)->refresh($event->project, true);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
