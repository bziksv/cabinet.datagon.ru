<?php

namespace App\Jobs\Monitoring;

use App\Exceptions\MonitoringChangesDateCancelledException;
use App\MonitoringChangesDate;
use App\MonitoringCompetitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitoringChangesDateQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int секунд — bulk по снимкам может занять несколько минут */
    public $timeout = 900;

    public $tries = 1;

    protected $record;

    protected array $request;

    public function __construct($record, $request)
    {
        $this->record = $record;
        $this->request = $request;
    }

    public function handle()
    {
        try {
            $freshRecord = MonitoringChangesDate::find($this->record->id);
            if (!$freshRecord) {
                return;
            }

            $this->record = $freshRecord;
            $this->record->update([
                'state' => 'in process',
            ]);

            @set_time_limit($this->timeout);
            @ini_set('memory_limit', '512M');

            $useBulk = (bool) config('cabinet-monitoring.competitors_changes_dates_use_bulk', true);

            if ($useBulk) {
                $record = $this->record;
                $competitors = MonitoringCompetitor::resolveChangesDateCompetitors(
                    (int) $this->record['monitoring_project_id'],
                    MonitoringCompetitor::normalizeChangesDateCompetitorInput($this->request['competitors'] ?? null)
                );
                $response = MonitoringCompetitor::calculateChangesByDateRange(
                    (int) $this->record['monitoring_project_id'],
                    (int) $this->request['region'],
                    (string) $this->request['dateRange'],
                    static function (int $done, int $total) use ($record) {
                        MonitoringCompetitor::updateChangesDateProgress($record, $done, $total);
                    },
                    $competitors
                );

                $this->record->refresh();
                $this->record->update([
                    'result' => json_encode($response, JSON_INVALID_UTF8_IGNORE),
                    'state' => 'ready',
                ]);

                MonitoringCompetitor::tryDispatchNextChangesDateReport(
                    (int) $this->record->monitoring_project_id
                );

                return;
            }

            $this->dispatchLegacyHelperJobs();
        } catch (MonitoringChangesDateCancelledException $e) {
            return;
        } catch (\Throwable $e) {
            Log::error('MonitoringChangesDateQueue failed', [
                'record_id' => $this->record->id ?? null,
                'message' => $e->getMessage(),
            ]);

            $this->record->update([
                'result' => '',
                'state' => 'fail',
            ]);

            MonitoringCompetitor::tryDispatchNextChangesDateReport(
                (int) $this->record->monitoring_project_id
            );
        }
    }

    /**
     * Старый путь: date × chunk(10) jobs в monitoring_helper (очень медленно на больших ядрах).
     */
    protected function dispatchLegacyHelperJobs(): void
    {
        $project = \App\MonitoringProject::where('id', $this->record['monitoring_project_id'])->first(['id', 'url']);
        $lr = \App\MonitoringSearchengine::where('id', '=', $this->request['region'])->pluck('lr')->toArray()[0];

        $words = \App\MonitoringKeyword::where('monitoring_project_id', $project['id'])->get(['query'])->toArray();
        $items = array_chunk(array_column($words, 'query'), 10);

        [$startDate, $endDate] = MonitoringCompetitor::parseCompetitorsDateRange($this->request['dateRange']);
        $period = new \Carbon\CarbonPeriod($startDate, $endDate);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        $hash = md5(microtime(true));
        $totalJobs = 0;
        foreach ($dates as $date) {
            foreach ($items as $keywords) {
                MonitoringHelperQueue::dispatch($date, $lr, $keywords, $hash)->onQueue('monitoring_helper');
                $totalJobs++;
            }
        }

        MonitoringCompetitor::updateChangesDateProgress($this->record, 0, $totalJobs);

        MonitoringWaitResultsQueue::dispatch($hash, $totalJobs, $project, $this->record)->onQueue('monitoring_wait');
    }
}
