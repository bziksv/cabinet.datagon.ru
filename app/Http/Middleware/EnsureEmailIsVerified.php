<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as Middleware;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Redirect;

class EnsureEmailIsVerified extends Middleware
{
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        if (app()->environment('local') || env('SKIP_EMAIL_VERIFICATION', false)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            // Flash/pending целей Метрики: keep на случай одноразового flash; pending сам живёт в сессии.
            if ($request->hasSession()) {
                $request->session()->keep(['ym_registered', 'ym_verified', 'verified']);
            }

            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : Redirect::route($redirectToRoute ?: 'verification.notice');
        }

        return $next($request);
    }
}
