<?php

namespace App\Http\Controllers;

use App\TextAnalyzerPublicShare;
use Illuminate\View\View;

class TextAnalyzerPublicShareController extends Controller
{
    public function show(string $token): View
    {
        if (!TextAnalyzerPublicShare::tableAvailable()) {
            abort(503, __('Public sharing is temporarily unavailable.'));
        }

        $share = TextAnalyzerPublicShare::where('token', $token)->first();

        if ($share === null || !$share->isActive()) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        $payload = $share->decodedPayload();
        $response = $payload['response'] ?? [];
        if ($response === []) {
            abort(404, __('This public link has expired or does not exist.'));
        }

        return view('text-analyse.public-share', [
            'share' => $share,
            'response' => $response,
            'request' => $payload['request'] ?? [],
            'url' => $payload['url'] ?? null,
            'shareMeta' => $payload['meta'] ?? [],
            'isPublicView' => true,
        ]);
    }
}
