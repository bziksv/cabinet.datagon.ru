<?php

namespace App\Jobs;

use App\Jobs\OccurrenceQueue;
use App\MonitoringOccurrence;
use App\MonitoringProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Фоновая постановка сбора частотности (в HTTP не делаем сотни dispatch() подряд).
 */
class EnqueueMonitoringOccurrenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    private $projectId;

    /** @var string */
    private $targetQueue;

    /** @var bool */
    private $missingOnly;

    public $timeout = 600;

    public function __construct(int $projectId, string $targetQueue = 'high', bool $missingOnly = false)
    {
        $this->projectId = $projectId;
        $this->targetQueue = $targetQueue;
        $this->missingOnly = $missingOnly;
    }

    public function handle(): void
    {
        $project = MonitoringProject::query()->find($this->projectId);
        if ($project === null) {
            return;
        }

        $engines = $project->searchengines()->where('engine', 'yandex')->with('location')->get();
        if ($engines->isEmpty()) {
            return;
        }

        $project->keywords()->orderBy('id')->chunkById(100, function ($keywords) use ($engines) {
            foreach ($engines as $engine) {
                $existingIds = [];
                if ($this->missingOnly) {
                    $existingIds = MonitoringOccurrence::query()
                        ->where('monitoring_searchengine_id', $engine->id)
                        ->whereIn('monitoring_keyword_id', $keywords->pluck('id'))
                        ->pluck('monitoring_keyword_id')
                        ->flip()
                        ->all();
                }

                foreach ($keywords as $keyword) {
                    if ($this->missingOnly && isset($existingIds[$keyword->id])) {
                        continue;
                    }

                    dispatch((new OccurrenceQueue($keyword, $engine))->onQueue($this->targetQueue));
                }
            }
        });
    }
}
