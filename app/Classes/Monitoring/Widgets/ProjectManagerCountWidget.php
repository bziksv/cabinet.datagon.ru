<?php


namespace App\Classes\Monitoring\Widgets;


use App\Http\Controllers\MonitoringProjectUserStatusController;

class ProjectManagerCountWidget extends WidgetsAbstract
{
    public function __construct()
    {
        $this->code = 'PROJECT_MANAGER_COUNT';
        $this->name = __('Project manager count');
        $this->icon = 'fas fa-user';
    }

    public function generateTitle(): string
    {
        return (string) MonitoringWidgetUserCounts::countByStatus(
            MonitoringProjectUserStatusController::STATUS_PM
        );
    }

    public function generateDesc(): string
    {
        return __('Project manager count');
    }
}
