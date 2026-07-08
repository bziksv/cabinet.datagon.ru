<?php


namespace App\Classes\Monitoring\Queues;


use App\Classes\Monitoring\MonitoringGoogleDepth;
use App\Classes\Monitoring\PositionLimit;
use App\Jobs\AutoUpdatePositionQueue;
use App\User;

class PositionsDispatch extends QueueDispatcher
{
    public function __construct(int $user, string $queue)
    {
        $this->user = User::find($user);
        $this->queue = $queue;
    }

    public function dispatch()
    {
        $queries = $this->getData();
        $this->countOff = $this->countLimits($queries);

        if (!$this->reserveLimits($this->countOff)) {
            return;
        }

        foreach ($queries as $ar) {
            dispatch((new AutoUpdatePositionQueue($ar['query'], $ar['region']))->onQueue($this->queue));
        }
    }

    /**
     * @param array<int, array{query: mixed, region: \App\MonitoringSearchengine}> $queries
     */
    private function countLimits(array $queries): int
    {
        $total = 0;

        foreach ($queries as $ar) {
            $engine = $ar['region'];
            if ($engine->engine === 'google') {
                $total += MonitoringGoogleDepth::limitsMultiplier($engine->google_depth);
            } else {
                $total += 1;
            }
        }

        return $total;
    }

    public function reserveLimits(int $count): bool
    {
        $this->countOff = max(0, $count);
        $limit = new PositionLimit($this->user['id']);
        if ($this->status = $limit->check($this->countOff)) {
            $this->msg = __('Job added to queue');

            return true;
        }

        $this->error = __('Limit exhausted');

        return false;
    }

    public function wasReserved(): bool
    {
        return (bool) $this->status;
    }
}
