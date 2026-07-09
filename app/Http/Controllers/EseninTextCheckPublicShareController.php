<?php

namespace App\Http\Controllers;

use App\EseninTextCheckPublicShare;
use Illuminate\View\View;

class EseninTextCheckPublicShareController extends Controller
{
    public function show(string $token): View
    {
        if (!EseninTextCheckPublicShare::tableAvailable()) {
            abort(503, __('Public sharing is temporarily unavailable.'));
        }

        $share = EseninTextCheckPublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $payload = $share->decodedPayload();
        $result = $payload['result'] ?? [];
        if ($result === []) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return view('pages.esenin-text-check-public-share', [
            'share' => $share,
            'result' => $result,
            'text' => $payload['text'] ?? '',
            'taskName' => $payload['name'] ?? '',
            'shareMeta' => $payload['meta'] ?? [],
            'modes' => \App\Services\EseninTextCheckService::MODES,
        ]);
    }
}
