<?php

namespace App\Http\Middleware;

use App\User;
use Closure;

/**
 * UI «Генерация / Макросы / Стоп-слова» временно только для админов (на доработке).
 */
class EnsureAiGenerationUiAdmin
{
    public function handle($request, Closure $next)
    {
        if (User::isUserAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson() || !$request->isMethod('GET')) {
            abort(403, 'Раздел AI-генерации на доработке');
        }

        return redirect()->route('ai.generation.story');
    }
}
