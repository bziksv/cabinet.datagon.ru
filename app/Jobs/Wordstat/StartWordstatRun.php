<?php
namespace App\Jobs\Wordstat;

use App\Wordstat\WordstatRun;
use App\Wordstat\WordstatRunSeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartWordstatRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    private int $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
    }

    public function handle(): void
    {
        $run = WordstatRun::query()->find($this->runId);
        if (! $run || ! $run->isActive()) {
            return;
        }
        $ids = WordstatRunSeed::query()->where('run_id', $run->id)->where('status', WordstatRunSeed::STATUS_QUEUED)->pluck('id');
        foreach ($ids as $id) {
            CollectWordstatSeed::dispatch((int) $id)->onQueue(config('cabinet-wordstat-keywords.queue'));
        }
    }
}
