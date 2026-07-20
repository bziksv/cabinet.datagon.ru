<?php

namespace App\Http\Controllers;

use App\Support\DemoCabinet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoCabinetController extends Controller
{
    public function enter(Request $request): RedirectResponse
    {
        if (! DemoCabinet::enabled()) {
            return redirect('/login')->withErrors(['email' => 'Демо-кабинет временно отключён.']);
        }

        $user = DemoCabinet::findUser();
        if (! $user) {
            return redirect('/login')->withErrors([
                'email' => 'Демо-кабинет ещё не подготовлен. Запустите: php artisan demo-cabinet:seed',
            ]);
        }

        // Если уже залогинен своим аккаунтом — полностью сбрасываем сессию и входим как демо
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('demo_cabinet', true);

        return redirect(DemoCabinet::homePath($user))->with('demo_cabinet_welcome', true);
    }

    public function exit(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $to = (string) config('cabinet-demo-cabinet.register_hint', 'https://titlo.ru/');

        return redirect()->away($to);
    }
}
