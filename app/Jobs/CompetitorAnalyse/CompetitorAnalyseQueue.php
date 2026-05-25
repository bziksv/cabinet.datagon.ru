<?php

namespace App\Jobs\CompetitorAnalyse;

use App\SearchCompetitors;
use App\Support\CompetitorAnalysisDebugLog;
use App\Support\CompetitorSearchRegions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompetitorAnalyseQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $request;

    private $userId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $request, int $userId)
    {
        $this->request = $request;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function getPageHash(): string
    {
        return (string) ($this->request['pageHash'] ?? '');
    }

    public function handle()
    {
        @set_time_limit((int) config('cabinet-competitor-analysis.job_max_execution_sec', 1200));
        @ini_set('max_execution_time', (string) config('cabinet-competitor-analysis.job_max_execution_sec', 1200));

        $pageHash = $this->getPageHash();

        CompetitorAnalysisDebugLog::info($pageHash, 'job.handle.start', [
            'user_id' => $this->userId,
            'phrases_lines' => substr_count((string) ($this->request['phrases'] ?? ''), "\n") + 1,
            'count' => (int) ($this->request['count'] ?? 0),
        ]);

        try {
            $maxRegions = (int) config('cabinet-competitor-analysis.max_regions', 5);
            $plan = $this->buildAnalysisPlan($maxRegions);

            if (count($plan) === 0) {
                CompetitorAnalysisDebugLog::error($pageHash, 'job.plan.empty');
                SearchCompetitors::markProgressFailed($pageHash, __('Invalid region'));

                return;
            }

            CompetitorAnalysisDebugLog::info($pageHash, 'job.plan.built', [
                'steps' => count($plan),
            ]);

            $analysis = new SearchCompetitors();
            $analysis->setUserId($this->userId);
            $analysis->setPhrases($this->request['phrases']);
            $analysis->setAnalysisPlan($plan);
            $analysis->setCount((int) $this->request['count']);
            $analysis->setPageHash($pageHash);
            $result = $analysis->analyseList();

            if ($result instanceof \Exception) {
                CompetitorAnalysisDebugLog::error($pageHash, 'job.analyseList.exception', [
                    'message' => $result->getMessage(),
                ]);
                Log::debug('competitor analyse job failed', [
                    'message' => $result->getMessage(),
                    'page_hash' => $pageHash,
                ]);
            } else {
                CompetitorAnalysisDebugLog::info($pageHash, 'job.handle.done');
            }
        } catch (Throwable $e) {
            CompetitorAnalysisDebugLog::error($pageHash, 'job.handle.throwable', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            Log::debug('competitor analyse job exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'page_hash' => $pageHash,
            ]);

            if ($pageHash !== '') {
                SearchCompetitors::markProgressFailed(
                    $pageHash,
                    __('An unexpected error has occurred, please contact the administrator')
                );
            }
        }
    }

    /**
     * @return array<int, array{engine: string, regions: array}>
     */
    protected function buildAnalysisPlan(int $maxRegions): array
    {
        if (!empty($this->request['analysis_plan']) && is_array($this->request['analysis_plan'])) {
            $engines = [];
            $regionsByEngine = [];

            foreach ($this->request['analysis_plan'] as $item) {
                $engine = CompetitorSearchRegions::normalizeEngine($item['engine'] ?? '');
                $engines[] = $engine;
                $regionsByEngine[$engine] = (array) ($item['regions'] ?? []);
            }

            return CompetitorSearchRegions::buildAnalysisPlanFromRequest(
                CompetitorSearchRegions::normalizeEnginesList($engines),
                $regionsByEngine,
                $maxRegions
            );
        }

        $engines = CompetitorSearchRegions::normalizeEnginesList(
            $this->request['search_engines'] ?? $this->request['search_engine'] ?? 'yandex'
        );

        $regionsByEngine = [];
        foreach ($engines as $engine) {
            $key = 'regions_' . $engine;
            $ids = (array) ($this->request[$key] ?? []);
            if (count($engines) === 1 && empty($ids)) {
                $ids = (array) ($this->request['regions'] ?? []);
                if (empty($ids) && !empty($this->request['region'])) {
                    $ids = [(string) $this->request['region']];
                }
            }
            $regionsByEngine[$engine] = $ids;
        }

        return CompetitorSearchRegions::buildAnalysisPlanFromRequest($engines, $regionsByEngine, $maxRegions);
    }
}
