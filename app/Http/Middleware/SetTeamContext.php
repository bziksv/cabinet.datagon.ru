<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class SetTeamContext
{
    public function handle($request, Closure $next)
    {
        apply_global_team_permissions();

        if (! Auth::guest()) {
            $user = Auth::user();
            if (! $user->relationLoaded('roles') || $user->roles->isEmpty()) {
                $user->unsetRelation('roles', 'permissions');
                $user->load('roles');
            }
        }

        return $next($request);
    }
}
