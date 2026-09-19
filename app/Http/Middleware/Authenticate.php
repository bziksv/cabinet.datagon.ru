<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            $path = $request->getRequestUri();
            if (is_string($path) && $path !== '' && $path !== '/' && strpos($path, '/login') !== 0) {
                return route('login', ['redirect' => $path]);
            }
            return route('login');
        }
    }
}
