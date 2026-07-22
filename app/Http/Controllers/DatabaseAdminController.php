<?php

namespace App\Http\Controllers;

use App\Services\Database\DatabaseInventoryService;
use App\Services\Database\TableOptimizeService;
use App\Services\Database\TableRowPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatabaseAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(DatabaseInventoryService $inventory, Request $request): View
    {
        $fresh = $request->boolean('fresh');
        $snapshot = $inventory->getSnapshot($fresh);

        $progress = $snapshot['date_probe_progress'] ?? null;

        return view('admin.database.index', [
            'snapshot' => $snapshot,
            'filter' => (string) $request->get('filter', 'all'),
            'dateProbeRemaining' => is_array($progress) ? (int) ($progress['remaining'] ?? 0) : null,
        ]);
    }

    public function refresh(DatabaseInventoryService $inventory): RedirectResponse
    {
        set_time_limit(120);
        $inventory->refreshMetadata();

        return redirect()
            ->route('admin.database.index')
            ->with('success', __('Database inventory metadata refreshed.'));
    }

    public function probeDates(DatabaseInventoryService $inventory, Request $request): RedirectResponse
    {
        set_time_limit(120);

        if ($request->boolean('reset')) {
            $inventory->resetDateProbeFlags();

            return redirect()
                ->route('admin.database.index')
                ->with('success', __('Database date scan reset.'));
        }

        $result = $inventory->probeDatesBatch();

        if ($result['remaining'] > 0) {
            $message = __('Database date scan batch done', [
                'batch' => $result['batch'],
                'remaining' => $result['remaining'],
            ]);
        } else {
            $message = __('Database date range scan completed.');
        }

        if (($result['light_count'] ?? 0) > 0) {
            $message .= ' ' . __('Database date scan light note', ['count' => $result['light_count']]);
        }

        return redirect()
            ->route('admin.database.index', ['filter' => 'all'])
            ->with('success', $message);
    }

    public function clearTable(string $table, DatabaseInventoryService $inventory): RedirectResponse
    {
        try {
            $result = $inventory->clearTable($table);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.database.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.database.index')
            ->with('success', __('Database table cleared', [
                'table' => $result['table'],
                'count' => $result['deleted'],
            ]));
    }

    public function optimizeTable(string $table, TableOptimizeService $optimizer, Request $request)
    {
        set_time_limit(max(120, (int) config('cabinet-database-admin.optimize_lock_seconds', 7200)));

        $wantsJson = $request->ajax() || $request->wantsJson() || $request->expectsJson();
        $filter = (string) $request->get('filter', 'all');

        try {
            // UI AJAX всегда в очередь — без блокировки запроса и без reload
            $result = $optimizer->requestOptimize($table, 'ui', $wantsJson);
        } catch (\InvalidArgumentException $e) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('admin.database.index', ['filter' => $filter])
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            if ($wantsJson) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }

            return redirect()
                ->route('admin.database.index', ['filter' => $filter])
                ->with('error', $e->getMessage());
        }

        if ($wantsJson) {
            $status = $optimizer->statusPayload($table);

            return response()->json([
                'ok' => true,
                'queued' => (bool) $result['queued'],
                'message' => $result['message'],
                'run' => $optimizer->serializeRun($result['run']),
                'size_mb' => $status['size_mb'],
                'data_free_mb' => $status['data_free_mb'],
                'optimize' => $status['optimize'],
            ]);
        }

        return redirect()
            ->route('admin.database.index', ['filter' => $filter, 'fresh' => 1])
            ->with('success', $result['message']);
    }

    public function optimizeStatus(string $table, TableOptimizeService $optimizer): JsonResponse
    {
        try {
            return response()->json(array_merge(['ok' => true], $optimizer->statusPayload($table)));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function previewRows(string $table, TableRowPreviewService $preview): JsonResponse
    {
        set_time_limit(max(15, (int) config('cabinet-database-admin.row_preview_timeout_seconds', 15)));

        try {
            $data = $preview->preview($table);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json($data);
    }
}
