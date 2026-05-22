<?php

namespace App\Http\Middleware;

use Closure;

/**
 * @deprecated Удаление неверифицированных — cron {@see \App\Classes\Cron\DeleteUnverifiedUsers}, не web-запросы.
 */
class DeleteUsersNoVerify
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
