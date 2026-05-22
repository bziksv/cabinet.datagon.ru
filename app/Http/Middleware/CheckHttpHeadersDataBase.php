<?php

namespace App\Http\Middleware;

use App\HttpHeader;
use Closure;

class CheckHttpHeadersDataBase
{
    /**
     * @var HttpHeader
     */
    protected $httpHeaders;

    /**
     * CheckHttpHeadersDataBase constructor.
     * @param HttpHeader $httpHeaders
     */
    public function __construct(HttpHeader $httpHeaders)
    {
        $this->httpHeaders = $httpHeaders;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Delete database row HttpHeaders where date create more n days.
     */
    public function terminate()
    {
        // На local и при DB на другом VPS DELETE по http_headers висит >60s (удалённая БД, большая таблица).
        if (app()->environment('local') || ! env('HTTP_HEADERS_CLEANUP_ON_REQUEST', true)) {
            return;
        }

        $this->httpHeaders->deleteData();
    }
}
