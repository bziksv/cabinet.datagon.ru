<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ack после reachGoal: pending-флаг в сессии нельзя снимать при рендере —
 * иначе цель сгорает, если tag.js не успел уйти (ушли с verify в почту).
 */
class YandexMetrikaGoalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function ack(Request $request): Response
    {
        $goal = (string) $request->input('goal', '');

        if ($goal === 'registered') {
            $request->session()->forget(['ym_pending_registered', 'ym_registered']);
        } elseif ($goal === 'verified') {
            $request->session()->forget(['ym_pending_verified', 'ym_verified', 'verified']);
        }

        return response()->noContent();
    }
}
