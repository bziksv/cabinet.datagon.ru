<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middlewares\PermissionMiddleware as SpatiePermissionMiddleware;

/**
 * Spatie permission check после team_id=1 и загрузки roles (иначе 403 на всех модулях в local).
 */
class EnsureTeamPermissionMiddleware extends SpatiePermissionMiddleware
{
    public function handle($request, Closure $next, $permission, $guard = null)
    {
        apply_global_team_permissions();

        if (! Auth::guest()) {
            $user = Auth::user();
            if (! $user->relationLoaded('roles') || $user->roles->isEmpty()) {
                $user->unsetRelation('roles', 'permissions');
                $user->load('roles');
            }
        }

        return parent::handle($request, $next, $permission, $guard);
    }
}
