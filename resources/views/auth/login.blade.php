@extends('layouts.auth')

@section('title', __('Login page'))

@section('content')
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h1 class="mb-0"><b id="auth-header">{{ __('Log in to the system') }}</b></h1>
            </div>
            <div class="card-body login-card-body">
                <form action="{{ url('/login') }}" method="POST" id="login-form">
                    @csrf

                    <div class="input-group mb-3">
                        <select id="select-language" name="lang"
                                class="form-select flags @error('lang') is-invalid @enderror">
                            @foreach($lang as $l)
                                <option value="{{ $l }}">
                                    @if($l == 'ru')
                                        Русский
                                    @else
                                        English
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('lang')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>

                    <div class="input-group mb-3">
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               placeholder="{{ __('E-Mail') }}" autocomplete="email" autofocus>
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="input-group mb-3">
                        <input id="password" type="password"
                               class="form-control @error('password') is-invalid @enderror" name="password"
                               placeholder="{{ __('Password') }}" autocomplete="current-password">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="remember"
                                   id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember" id="remember-me-label">
                                {{ __('Remember Me') }}
                            </label>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary" id="login-button">{{ __('Login') }}</button>
                    </div>
                </form>

                <div class="social-auth-links text-center mt-2 mb-3">
                    <div class="row g-2">
                        @if (Route::has('password.request'))
                            <div class="col-6">
                                <a href="{{ route('password.request') }}" class="btn btn-danger w-100">
                                    <i class="fas fa-key me-2"></i> {{ __('Forgot Your Password?') }}
                                </a>
                            </div>
                        @endif

                        @if (Route::has('register'))
                            <div class="col-6">
                                <a href="{{ route('register') }}" class="btn btn-primary w-100">
                                    <i class="fas fa-registered me-2"></i> {{ __('Register') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        if (navigator.language === 'en') {
            $('#select-language').val('en')
        } else {
            $('#select-language').val('ru')
        }

        @if(config('app.env') !== 'local')
        $(".flags").select2({
            minimumResultsForSearch: Infinity,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }
                var baseUrl = "/img/flags";
                var $state = $(
                    '<span><img src="' + baseUrl + '/' + state.element.value.toLowerCase() + '.png" class="img-flag" /> ' + state.text + '</span>'
                );
                return $state;
            }
        });
        @endif
    </script>
    <script>
        $(document).ready(function () {
            $('#login-form').on('submit', function () {
                $('#login-button').prop('disabled', true).html('Вход… подождите');
            });

            $('#select-language').on('change', function () {
                if ($(this).val() === 'en') {
                    setEngLanguage()
                } else {
                    setRuLanguage()
                }
            })

            function setEngLanguage() {
                $('#password').attr('placeholder', 'Password')
                $('#remember-me-label').html('Remember me')
                $('#login-button').html('Login')
                $('.social-auth-links a.btn-danger').html('<i class="fas fa-key me-2"></i> Forgot your password?')
                $('.social-auth-links a.btn-primary').html('<i class="fas fa-registered me-2"></i> Register a new user')
                $('#auth-header').html('Log in to the system')
            }

            function setRuLanguage() {
                $('#password').attr('placeholder', 'Пароль')
                $('#remember-me-label').html('Запомнить меня')
                $('#login-button').html('Войти')
                $('.social-auth-links a.btn-danger').html('<i class="fas fa-key me-2"></i> Забыли пароль?')
                $('.social-auth-links a.btn-primary').html('<i class="fas fa-registered me-2"></i> Зарегистрировать нового пользователя')
                $('#auth-header').html('Вход в систему')
            }
        })
    </script>
    <script>
        if (localStorage.getItem('_user_metrics_redbox') != '' && localStorage.getItem('_user_metrics_redbox') != undefined) {
            let registerButtons = $("a[href='{{ route('register') }}']");

            $.each(registerButtons, function (k, element) {
                element.href += localStorage.getItem('_user_metrics_redbox');
            })

        } else if (new URL(window.location.href)['search'] != '' && new URL(window.location.href)['search'] != undefined) {
            let registerButtons = $("a[href='{{ route('register') }}']");

            $.each(registerButtons, function (k, element) {
                element.href += new URL(window.location.href)['search'];
            })
        }
    </script>
@endsection
