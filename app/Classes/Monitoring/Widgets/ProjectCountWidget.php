<?php


namespace App\Classes\Monitoring\Widgets;

class ProjectCountWidget extends WidgetsAbstract
{
    public function __construct()
    {
        $this->code = 'PROJECT_COUNT';
        $this->name = __('Projects count');
        $this->link = route('monitoring.index');
        $this->icon = 'fas fa-tasks';
    }

    public function generateTitle(): string
    {
        return (string) MonitoringWidgetUserCounts::monitoringProjectCount();
    }

    public function generateDesc(): string
    {
        return __('Projects count');
    }

}
