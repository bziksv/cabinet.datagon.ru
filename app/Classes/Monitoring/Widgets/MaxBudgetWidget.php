<?php


namespace App\Classes\Monitoring\Widgets;


use App\Classes\Monitoring\ProjectsStatisticFacade;

class MaxBudgetWidget extends WidgetsAbstract
{
    public function __construct()
    {
        $this->code = 'MAX_BUDGET';
        $this->name = __('Max budget');
        $this->icon = 'fas fa-ruble-sign';
    }

    public function generateTitle(): string
    {
        $projects = ProjectsStatisticFacade::getTodayProjects();

        if ($projects === null) {
            return '0';
        }

        return (string) $projects->pluck('budget')->sum();
    }

    public function generateDesc(): string
    {
        return __('Max budget');
    }
}
