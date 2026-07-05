<?php

namespace App\Services\Queue;

use App\MonitoringChangesDate;
use App\Support\ClusterProgress;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueInventoryService
{
    private const CACHE_KEY = 'cabinet.queue-inventory.snapshot';

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(bool $fresh = false): array
    {
        $ttl = (int) config('cabinet-queue-admin.snapshot_cache_seconds', 30);

        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, $ttl, function () {
            return $this->buildSnapshot();
        });
    }

    public function refreshSnapshot(): array
    {
        Cache::forget(self::CACHE_KEY);
        $snapshot = $this->buildSnapshot();
        $ttl = (int) config('cabinet-queue-admin.snapshot_cache_seconds', 30);
        Cache::put(self::CACHE_KEY, $snapshot, $ttl);

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSnapshot(): array
    {
        $queues = $this->buildQueueRows();
        $clusters = ClusterProgress::listActiveProgressIds(80);
        $monitoring = $this->buildMonitoringReports();
        $failedJobs = $this->buildFailedJobs();

        $totalJobs = array_sum(array_column($queues, 'total'));
        $reservedJobs = array_sum(array_column($queues, 'reserved'));
        $oldestJobAt = null;

        foreach ($queues as $row) {
            if (! empty($row['oldest_at']) && ($oldestJobAt === null || $row['oldest_at'] < $oldestJobAt)) {
                $oldestJobAt = $row['oldest_at'];
            }
        }

        $stuckClusters = array_values(array_filter($clusters, static function (array $row) {
            return ($row['status'] ?? '') === 'stuck';
        }));
        $orphanSummary = ClusterProgress::orphanSummary();

        $helperBacklog = 0;
        foreach ($queues as $row) {
            if (($row['queue'] ?? '') === 'monitoring_helper' || Str::endsWith((string) ($row['queue'] ?? ''), 'monitoring_helper')) {
                $helperBacklog += (int) ($row['total'] ?? 0);
            }
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'database' => (string) config('database.connections.mysql.database', ''),
            'host' => (string) config('database.connections.mysql.host', ''),
            'cluster_queue_prefix' => (string) config('cabinet-cluster.queue_prefix', ''),
            'summary' => [
                'total_jobs' => $totalJobs,
                'reserved_jobs' => $reservedJobs,
                'queue_count' => count($queues),
                'failed_jobs_total' => (int) DB::table('failed_jobs')->count(),
                'failed_jobs_recent' => count($failedJobs),
                'oldest_job_at' => $oldestJobAt,
                'stuck_clusters' => count($stuckClusters),
                'orphan_clusters' => (int) ($orphanSummary['orphan_progress'] ?? 0),
                'orphan_cluster_rows' => (int) ($orphanSummary['orphan_rows'] ?? 0),
                'active_monitoring_reports' => count(array_filter($monitoring, static function (array $row) {
                    return in_array($row['state'] ?? '', ['in queue', 'in process', 'pending'], true);
                })),
                'monitoring_helper_backlog' => $helperBacklog,
            ],
            'queues' => $queues,
            'clusters' => $clusters,
            'monitoring_reports' => $monitoring,
            'failed_jobs' => $failedJobs,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildQueueRows(): array
    {
        $prefix = (string) config('cabinet-cluster.queue_prefix', '');
        $labels = config('cabinet-queue-admin.queue_labels', []);

        $rows = DB::table('jobs')
            ->select('queue')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved')
            ->selectRaw('MIN(FROM_UNIXTIME(created_at)) as oldest_at')
            ->selectRaw('MAX(FROM_UNIXTIME(created_at)) as newest_at')
            ->groupBy('queue')
            ->orderByDesc('total')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $queue = (string) $row->queue;
            $baseQueue = $prefix !== '' && Str::startsWith($queue, $prefix)
                ? substr($queue, strlen($prefix))
                : $queue;
            $meta = $labels[$baseQueue] ?? $labels[$queue] ?? null;
            $warnAbove = (int) ($meta['warn_above'] ?? 100);
            $total = (int) $row->total;

            $out[] = [
                'queue' => $queue,
                'base_queue' => $baseQueue,
                'label' => $meta['label'] ?? $queue,
                'module' => $meta['module'] ?? '—',
                'total' => $total,
                'reserved' => (int) $row->reserved,
                'available' => $total - (int) $row->reserved,
                'oldest_at' => $row->oldest_at ? (string) $row->oldest_at : null,
                'newest_at' => $row->newest_at ? (string) $row->newest_at : null,
                'age_minutes' => $row->oldest_at ? Carbon::parse($row->oldest_at)->diffInMinutes(now()) : null,
                'severity' => $total >= $warnAbove ? 'danger' : ($total >= max(1, (int) round($warnAbove / 2)) ? 'warning' : 'ok'),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildMonitoringReports(): array
    {
        $limit = (int) config('cabinet-queue-admin.monitoring_reports_limit', 30);
        $staleMinutes = max(5, (int) config('cabinet-monitoring.competitors_changes_dates_stale_minutes', 20));

        $records = MonitoringChangesDate::query()
            ->with(['mainProject:id,url,name'])
            ->whereIn('state', ['in queue', 'in process', 'pending', 'fail'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $out = [];

        foreach ($records as $record) {
            $requestData = json_decode((string) $record->request, true) ?: [];
            $progressDone = (int) ($requestData['progress_done'] ?? 0);
            $progressTotal = (int) ($requestData['progress_total'] ?? 0);
            $stale = $record->state === 'in process'
                && $record->updated_at
                && $record->updated_at->diffInMinutes(now()) >= $staleMinutes
                && ($progressTotal === 0 || $progressDone < $progressTotal);

            $out[] = [
                'id' => (int) $record->id,
                'project_id' => (int) $record->monitoring_project_id,
                'host' => optional($record->mainProject)->url ?: optional($record->mainProject)->name,
                'state' => (string) $record->state,
                'range' => (string) $record->range,
                'progress_done' => $progressDone,
                'progress_total' => $progressTotal,
                'updated_at' => optional($record->updated_at)->toDateTimeString(),
                'created_at' => optional($record->created_at)->toDateTimeString(),
                'stale' => $stale,
                'severity' => $stale ? 'danger' : (in_array($record->state, ['in queue', 'in process', 'pending'], true) ? 'warning' : 'muted'),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildFailedJobs(): array
    {
        $limit = (int) config('cabinet-queue-admin.failed_jobs_limit', 25);

        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get(['id', 'connection', 'queue', 'failed_at', 'exception'])
            ->map(static function ($row) {
                $exception = (string) ($row->exception ?? '');
                $class = '';
                if (preg_match('/^([^\n:]+)/', $exception, $m)) {
                    $class = trim($m[1]);
                }

                return [
                    'id' => (int) $row->id,
                    'connection' => (string) ($row->connection ?? ''),
                    'queue' => (string) $row->queue,
                    'failed_at' => (string) $row->failed_at,
                    'exception_class' => $class,
                    'exception_preview' => mb_substr(preg_replace('/\s+/', ' ', $exception), 0, 180),
                ];
            })
            ->all();
    }

    /**
     * @return array{deleted:int,queue:string}
     */
    public function purgeQueue(string $queue): array
    {
        $this->assertKnownQueue($queue);

        $deleted = (int) DB::table('jobs')->where('queue', $queue)->delete();
        $this->refreshSnapshot();

        return ['deleted' => $deleted, 'queue' => $queue];
    }

    /**
     * @return array{cancelled:bool,progress_id:string,details:array<string,mixed>}
     */
    public function cancelCluster(string $progressId): array
    {
        $progressId = trim($progressId);
        if ($progressId === '' || ! preg_match('/^[a-f0-9]{32}$/', $progressId)) {
            throw new \InvalidArgumentException(__('Invalid cluster progress id.'));
        }

        $details = ClusterProgress::cancelProgress($progressId);
        $this->refreshSnapshot();

        return [
            'cancelled' => true,
            'progress_id' => $progressId,
            'details' => $details,
        ];
    }

    public function deleteJob(int $jobId): bool
    {
        $deleted = (int) DB::table('jobs')->where('id', $jobId)->delete() > 0;
        $this->refreshSnapshot();

        return $deleted;
    }

    /**
     * @return array{cancelled:bool,id:int}
     */
    public function cancelMonitoringReport(int $recordId): array
    {
        $record = MonitoringChangesDate::find($recordId);
        if (! $record) {
            throw new \InvalidArgumentException(__('Report not found.'));
        }

        $record->update([
            'state' => 'fail',
            'result' => '',
        ]);

        \App\MonitoringCompetitor::cancelQueuedChangesDateReport($recordId);
        \App\MonitoringCompetitor::tryDispatchNextChangesDateReport((int) $record->monitoring_project_id);

        $this->refreshSnapshot();

        return ['cancelled' => true, 'id' => $recordId];
    }

    /**
     * @return array{deleted_progress:int,deleted_rows:int}
     */
    public function purgeOrphanClusters(int $olderThanDays = 0): array
    {
        $result = ClusterProgress::purgeOrphans($olderThanDays);
        $this->refreshSnapshot();

        return $result;
    }

    protected function assertKnownQueue(string $queue): void
    {
        if ($queue === '') {
            throw new \InvalidArgumentException(__('Queue name is required.'));
        }

        $prefix = (string) config('cabinet-cluster.queue_prefix', '');
        $labels = config('cabinet-queue-admin.queue_labels', []);
        $baseNames = array_keys($labels);
        $allowed = $baseNames;
        foreach ($baseNames as $name) {
            $allowed[] = $prefix . $name;
        }

        if (! in_array($queue, $allowed, true)) {
            throw new \InvalidArgumentException(__('Queue is not allowed for purge.'));
        }
    }
}
