<?php

namespace App\Jobs;

use App\SeoReports\SeoReport;
use App\Services\SeoReports\SeoReportGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSeoReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $tries = 2;

    /** @var int */
    public $timeout = 300;

    /** @var int */
    public $reportId;

    public function __construct(int $reportId)
    {
        $this->reportId = $reportId;
        $this->onQueue((string) config('seo-reports.queue', 'default'));
    }

    public function handle(SeoReportGeneratorService $generator): void
    {
        $report = SeoReport::query()->with('project')->find($this->reportId);
        if (!$report || !$report->project) {
            return;
        }

        $result = $generator->generate($report);
        if (empty($result['ok'])) {
            Log::warning('seo report generate job failed', [
                'report_id' => $this->reportId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        $report = SeoReport::query()->find($this->reportId);
        if (!$report) {
            return;
        }

        $report->status = SeoReport::STATUS_FAILED;
        $report->fail_reason = mb_substr($e->getMessage(), 0, 240);
        $report->save();
    }
}
