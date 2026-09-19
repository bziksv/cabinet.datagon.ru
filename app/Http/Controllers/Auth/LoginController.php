<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    use AuthenticatesUsers {
        login as protected traitLogin;
        logout as protected traitLogout;
    }
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }


    /**
     * @return View
     */
    public function showLoginForm(): View
    {
        static $langCache = null;

        if ($langCache === null) {
            $langCache = collect(Storage::disk('lang')->files())->map(function ($val) {
                return Str::before($val, '.');
            });
        }

        return view('auth.login', [
            'lang' => $langCache,
            'redirect' => $this->safeRedirectPath(request()->query('redirect')),
        ]);
    }

    /**
     * После логина: session intended, иначе ?redirect=/path, иначе /.
     *
     * @return string
     */
    public function redirectTo()
    {
        $fromQuery = $this->safeRedirectPath(request()->input('redirect'));
        if ($fromQuery !== null) {
            return $fromQuery;
        }
        return '/';
    }

    /**
     * Только внутренний path кабинета (без open redirect).
     */
    protected function safeRedirectPath($raw): ?string
    {
        if (!is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '' || $raw[0] !== '/') {
            return null;
        }
        if (strpos($raw, '//') !== false || strpos($raw, '\\') !== false) {
            return null;
        }
        if (!preg_match('#^/[A-Za-z0-9/_\-.?=&%]*$#', $raw)) {
            return null;
        }
        if (strpos($raw, '/login') === 0 || strpos($raw, '/logout') === 0) {
            return null;
        }
        return $raw;
    }

    public function logout(Request $request)
    {
        session()->forget([
            'cabinet_menu_modules',
            'cabinet_menu_modules_v2',
            'cabinet_menu_modules_v3',
            'cabinet_menu_modules_v4',
            'cabinet_menu_modules_v4_stamp',
            'cabinet_home_projects',
        ]);

        return $this->traitLogout($request);
    }

    /**
     * Удалённая БД — авторизация может идти долго; не обрывать на 60s.
     */
    public function login(Request $request)
    {
        set_time_limit(300);

        $response = $this->traitLogin($request);

        if (Auth::check()) {
            session()->forget([
                'cabinet_menu_modules',
                'cabinet_menu_modules_v2',
            'cabinet_menu_modules_v3',
            'cabinet_menu_modules_v4',
                'cabinet_menu_modules_v4_stamp',
                'cabinet_home_projects',
            ]);
        }

        return $response;
    }

}
