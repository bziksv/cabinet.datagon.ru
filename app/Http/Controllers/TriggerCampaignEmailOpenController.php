<?php

namespace App\Http\Controllers;

use App\Services\Finance\TriggerCampaignService;
use App\TriggerCampaignDispatch;
use Illuminate\Http\Response;

class TriggerCampaignEmailOpenController extends Controller
{
    private const PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z5BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function pixel(string $token, TriggerCampaignService $campaigns): Response
    {
        $dispatch = TriggerCampaignDispatch::query()
            ->where('tracking_token', $token)
            ->first();

        if ($dispatch !== null && !$dispatch->is_test) {
            $campaigns->markDispatchOpened($dispatch);
        }

        return response(base64_decode(self::PIXEL_PNG), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
