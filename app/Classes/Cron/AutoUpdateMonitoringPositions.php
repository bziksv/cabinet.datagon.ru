<?php


namespace App\Classes\Cron;

use App\Classes\Monitoring\Queues\PositionsDispatch;
use App\Jobs\AutoUpdatePositionQueue;
use App\User;

class AutoUpdateMonitoringPositions
{
    private $engine;

    public function __construct($engine)
    {
        $this->engine = $engine;
    }

    public function __invoke()
    {
        $engine = $this->engine;
        $project = $engine->project;

        if (!$project) {
            return;
        }

        $creator = User::query()->find((int) $project->creator);
        if ($creator instanceof User && $creator->onFreeTariff()) {
            return;
        }

        $queue = new PositionsDispatch($project->creator, 'position_low');
        foreach ($project->keywords as $query)
            $queue->addQueryWithRegion($query, $engine);

        $queue->dispatch();
    }
}
