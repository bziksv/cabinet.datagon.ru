<?php

namespace App\Jobs\Relevance;

use App\Relevance;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RunRelevanceAnalyseQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Без лимита — полный анализ может занимать несколько минут. */
    public $timeout = 0;

    public $relevance;

    /**
     * Create a new job instance.
     *
     * @param Relevance $relevance
     */
    public function __construct(Relevance $relevance)
    {
        $this->relevance = $relevance;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->relevance->analysis();
        } catch (\Throwable $exception) {
            $this->relevance->saveError($exception);
            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->relevance) {
            $this->relevance->saveError($exception);
        }
    }
}
