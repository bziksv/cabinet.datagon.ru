<?php


namespace App\Classes\Monitoring\Widgets;


use App\Http\Controllers\MonitoringProjectUserStatusController;

class SeoUserCountWidget extends WidgetsAbstract
{
    public function __construct()
    {
        $this->code = 'SEO_USER_COUNT';
        $this->name = __('Seo user count');
        $this->icon = 'fas fa-laptop-code';
    }

    public function generateTitle(): string
    {
        return (string) MonitoringWidgetUserCounts::countByStatus(
            MonitoringProjectUserStatusController::STATUS_SEO
        );
    }

    public function generateDesc(): string
    {
        return __('Seo user count');
    }
}
