<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;

/**
 * Throttle Integration API по api_key_id (POST жёстче, чем GET).
 * Laravel 6: без Facade RateLimiter — через Illuminate\Cache\RateLimiter.
 */
class ThrottleIntegrationApiKey
{
    /** @var RateLimiter */
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next)
    {
        $key = $request->attributes->get('integration_api_key');
        $id = $key && isset($key->id) ? (int) $key->id : 0;
        if ($id <= 0) {
            return $next($request);
        }

        $isWrite = in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $max = $isWrite
            ? (int) config('integration_api.throttle_write_per_minute', 60)
            : (int) config('integration_api.throttle_read_per_minute', 300);
        if ($max < 1) {
            $max = 1;
        }

        $bucket = 'integration_api:' . $id . ':' . ($isWrite ? 'w' : 'r');
        if ($this->limiter->tooManyAttempts($bucket, $max)) {
            return response()->json([
                'error' => 'rate_limited',
                'message' => 'Too many requests for this API key',
                'retry_after' => $this->limiter->availableIn($bucket),
            ], 429);
        }

        $this->limiter->hit($bucket, 60);

        return $next($request);
    }
}
