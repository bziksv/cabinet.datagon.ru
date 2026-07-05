<?php

namespace App\Http\Controllers;

use App\Services\Queue\QueueInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(QueueInventoryService $inventory, Request $request): View
    {
        $fresh = $request->boolean('fresh');
        $snapshot = $inventory->getSnapshot($fresh);
        $filter = (string) $request->get('filter', 'all');

        return view('admin.queue.index', [
            'snapshot' => $snapshot,
            'filter' => $filter,
        ]);
    }

    public function refresh(QueueInventoryService $inventory): RedirectResponse
    {
        $inventory->refreshSnapshot();

        return redirect()
            ->route('admin.queue.index', ['fresh' => 1])
            ->with('success', __('Queue snapshot refreshed.'));
    }

    public function cancelCluster(Request $request, QueueInventoryService $inventory): RedirectResponse
    {
        $progressId = (string) $request->input('progress_id', '');

        try {
            $result = $inventory->cancelCluster($progressId);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.queue.index')
                ->with('error', $e->getMessage());
        }

        $details = $result['details'];

        return redirect()
            ->route('admin.queue.index')
            ->with('success', __('Cluster cancelled', [
                'id' => substr($progressId, 0, 8) . '…',
                'rows' => $details['removed_queue_rows'] ?? 0,
                'wait' => $details['removed_wait_jobs'] ?? 0,
                'child' => $details['removed_child_jobs'] ?? 0,
            ]));
    }

    public function purgeQueue(Request $request, QueueInventoryService $inventory): RedirectResponse
    {
        $queue = (string) $request->input('queue', '');

        try {
            $result = $inventory->purgeQueue($queue);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.queue.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.queue.index')
            ->with('success', __('Queue purged', [
                'queue' => $queue,
                'count' => $result['deleted'],
            ]));
    }

    public function deleteJob(Request $request, QueueInventoryService $inventory): RedirectResponse
    {
        $jobId = (int) $request->input('job_id', 0);
        if ($jobId <= 0) {
            return redirect()
                ->route('admin.queue.index')
                ->with('error', __('Invalid job id.'));
        }

        $inventory->deleteJob($jobId);

        return redirect()
            ->route('admin.queue.index')
            ->with('success', __('Job deleted', ['id' => $jobId]));
    }

    public function cancelMonitoringReport(Request $request, QueueInventoryService $inventory): RedirectResponse
    {
        $recordId = (int) $request->input('record_id', 0);

        try {
            $inventory->cancelMonitoringReport($recordId);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.queue.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.queue.index')
            ->with('success', __('Monitoring report cancelled', ['id' => $recordId]));
    }

    public function purgeOrphanClusters(Request $request, QueueInventoryService $inventory): RedirectResponse
    {
        $days = max(0, (int) $request->input('older_than_days', 0));
        $result = $inventory->purgeOrphanClusters($days);

        return redirect()
            ->route('admin.queue.index')
            ->with('success', __('Cluster orphans purged', [
                'progress' => $result['deleted_progress'],
                'rows' => $result['deleted_rows'],
            ]));
    }
}
