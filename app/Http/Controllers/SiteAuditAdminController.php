<?php

namespace App\Http\Controllers;

use App\Services\SiteAudit\SiteAuditGlobalCap;
use App\Support\SiteAuditAdminRuntimeSettings;
use App\Support\SiteAuditAdminStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteAuditAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(): View
    {
        $registry = SiteAuditAdminStats::snapshot();
        $stats = $registry['summary'];
        $capacity = SiteAuditAdminRuntimeSettings::capacitySnapshot();
        $fields = SiteAuditAdminRuntimeSettings::FIELDS;

        return view('pages.site-audit-admin', compact('registry', 'stats', 'capacity', 'fields'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (SiteAuditAdminRuntimeSettings::FIELDS as $key => $meta) {
            $rules[$key] = 'required|integer|min:' . (int) $meta['min'] . '|max:' . (int) $meta['max'];
        }
        $validated = $request->validate($rules);

        SiteAuditAdminRuntimeSettings::put($validated);
        $promoted = SiteAuditGlobalCap::promoteWaiting();

        $msg = 'Лимиты сохранены';
        if ($promoted > 0) {
            $msg .= '. Снято с ожидания: ' . number_format($promoted, 0, '', ' ');
        }

        return redirect()
            ->route('pages.site-audit.admin')
            ->with('status', $msg);
    }

    public function promoteWaiting(): RedirectResponse
    {
        $reclaimed = SiteAuditGlobalCap::reclaimStale();
        $kicked = SiteAuditGlobalCap::kickStuckActive();
        $promoted = SiteAuditGlobalCap::promoteWaiting();

        return redirect()
            ->route('pages.site-audit.admin')
            ->with('status', sprintf(
                'Очередь: reclaimed=%d, kicked=%d, promoted=%d (active %d / max %d)',
                $reclaimed,
                $kicked,
                $promoted,
                SiteAuditGlobalCap::countActive(),
                SiteAuditGlobalCap::maxActive()
            ));
    }
}
