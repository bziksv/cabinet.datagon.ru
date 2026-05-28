<?php

namespace App\Http\Controllers;

use App\Services\Database\DatabaseInventoryService;
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

    public function previewRows(string $table, TableRowPreviewService $preview): JsonResponse
    {
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
