<?php

namespace App\Http\Controllers;

use App\MonitoringPublicShare;
use Illuminate\View\View;

class MonitoringPublicShareController extends Controller
{
    public function show(string $token): View
    {
        if (!MonitoringPublicShare::tableAvailable()) {
            abort(503, __('Public sharing is temporarily unavailable.'));
        }

        $share = MonitoringPublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $payload = $share->decodedPayload();
        $report = $payload['report'] ?? [];
        if ($report === []) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return view('monitoring-v2.public-share', [
            'share' => $share,
            'report' => $report,
            'shareMeta' => $payload['meta'] ?? [],
            'isPublicView' => true,
        ]);
    }
}
