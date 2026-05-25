<?php

namespace App\Jobs\Cluster;

use App\Cluster;
use App\Support\ClusterAnalysisDebugLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartClusterAnalyseQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $request;

    private $user;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request, $user)
    {
        $this->request = $request;
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $progressId = (string) ($this->request['progressId'] ?? '');
        ClusterAnalysisDebugLog::info($progressId, 'job.startCluster.handle.start', [
            'user_id' => $this->user->id ?? null,
        ]);

        try {
            $cluster = new Cluster($this->request, $this->user);
            $cluster->startAnalysis();
            ClusterAnalysisDebugLog::info($progressId, 'job.startCluster.handle.done');
        } catch (\Throwable $e) {
            ClusterAnalysisDebugLog::error($progressId, 'job.startCluster.handle.failed', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
