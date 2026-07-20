<?php

namespace App\Http\Middleware;

use App\Support\DemoCabinet;
use Closure;
use Illuminate\Http\Request;

/**
 * Демо-кабинет: GET можно, мутации (кроме logout) — нет.
 */
class DemoCabinetReadonly
{
    public function handle(Request $request, Closure $next)
    {
        if (! DemoCabinet::isCurrentUser()) {
            return $next($request);
        }

        $method = strtoupper($request->getMethod());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if (DemoCabinet::allowsMutatingRequest($request)) {
            return $next($request);
        }

        $message = DemoCabinet::blockMessage();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => 'demo_readonly',
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->back()
            ->with('demo_cabinet_error', $message);
    }
}
